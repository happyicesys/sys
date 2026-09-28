<?php

namespace App\Console\Commands;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Services\CardSettlement\ParserRegistry;
use App\Services\CardSettlement\SettlementParseException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Seed: fill card_settlement_rows.card_last4 for reports ingested before the
 * parser read the card column (2026-09-28), by re-parsing each report's own
 * stored file. Only NULL cells are written, so a re-run is a no-op.
 *
 * A stored row is updated only when the re-parsed line at the same row_no
 * has the same terminal, date and amount. One disagreement skips the whole
 * report: a shifted row_no would put every card on the wrong sale.
 *
 *   php artisan card-settlement:backfill-card-last4              # dry run
 *   php artisan card-settlement:backfill-card-last4 --report=60  # one report
 *   php artisan card-settlement:backfill-card-last4 --apply
 */
class BackfillCardSettlementCardLast4 extends Command
{
    protected $signature = 'card-settlement:backfill-card-last4
        {--report= : report id, this report only}
        {--apply : write (default is a dry run)}';

    protected $description = 'Fill card_last4 on already-ingested NETS lines from their stored report files (dry-run by default)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $reports = CardSettlementReport::query()
            ->with('attachment')
            ->when($this->option('report'), fn ($q, $id) => $q->whereKey($id))
            ->whereHas('rows', fn ($q) => $q->whereNull('card_last4'))
            ->orderBy('id')
            ->get();

        $totals = ['reports' => 0, 'filled' => 0, 'no_card' => 0, 'skipped_reports' => 0];

        foreach ($reports as $report) {
            try {
                $parsed = $this->parse($report);
            } catch (\Throwable $e) {
                $this->warn("Report {$report->id} ({$report->original_filename}): cannot read file — {$e->getMessage()}");
                $totals['skipped_reports']++;

                continue;
            }

            $byRowNo = [];
            foreach ($parsed->rows as $p) {
                $byRowNo[$p->rowNo] = $p;
            }

            $stored = CardSettlementRow::query()
                ->where('card_settlement_report_id', $report->id)
                ->whereNull('card_last4')
                ->get(['id', 'row_no', 'terminal_id', 'transaction_date', 'amount_cents']);

            $fill = [];
            $mismatch = null;
            foreach ($stored as $row) {
                $p = $byRowNo[$row->row_no] ?? null;
                if (! $p
                    || $p->terminalId !== $row->terminal_id
                    || $p->transactionDate !== $row->transaction_date->toDateString()
                    || $p->amountCents !== (int) $row->amount_cents) {
                    $mismatch = $row->row_no;
                    break;
                }
                if ($p->cardLast4 !== null) {
                    $fill[$row->id] = $p->cardLast4;
                }
            }

            if ($mismatch !== null) {
                $this->warn("Report {$report->id} ({$report->original_filename}): line {$mismatch} differs from the stored row — report skipped.");
                $totals['skipped_reports']++;

                continue;
            }

            $totals['reports']++;
            $totals['filled'] += count($fill);
            $totals['no_card'] += $stored->count() - count($fill);
            $this->line(sprintf('Report %d (%s): %d of %d line(s) carry a card', $report->id, $report->original_filename, count($fill), $stored->count()));

            if ($apply) {
                $this->write($fill);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d report(s), %d line(s) %s, %d without a card number, %d report(s) skipped.',
            $apply ? 'Applied' : 'Dry run',
            $totals['reports'],
            $totals['filled'],
            $apply ? 'filled' : 'to fill',
            $totals['no_card'],
            $totals['skipped_reports'],
        ));

        return $totals['skipped_reports'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function parse(CardSettlementReport $report)
    {
        $attachment = $report->attachment;
        if (! $attachment || blank($attachment->local_url)) {
            throw new SettlementParseException('no stored file');
        }

        // Same staging as MatchCardSettlementReport::ingest(): the parser wants a local path.
        $tmp = tempnam(sys_get_temp_dir(), 'card-settlement-');
        try {
            file_put_contents($tmp, Storage::disk($report->fileDisk())->get($attachment->local_url));

            return ParserRegistry::for($report->provider)->parse($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * One bound UPDATE … CASE per 500 rows instead of one per row; the NULL
     * guard keeps a concurrent ingest or a second run from being overwritten.
     *
     * @param  array<int, string>  $fill  row id → last 4
     */
    protected function write(array $fill): void
    {
        foreach (array_chunk($fill, 500, true) as $chunk) {
            $bindings = [];
            foreach ($chunk as $id => $last4) {
                array_push($bindings, $id, $last4);
            }
            $ids = array_keys($chunk);

            DB::update(
                'UPDATE card_settlement_rows SET card_last4 = CASE id'.str_repeat(' WHEN ? THEN ?', count($chunk)).' END'
                .' WHERE card_last4 IS NULL AND id IN ('.implode(',', array_fill(0, count($ids), '?')).')',
                [...$bindings, ...$ids]
            );
        }
    }
}
