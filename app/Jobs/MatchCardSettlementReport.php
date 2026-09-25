<?php

namespace App\Jobs;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminalBinding;
use App\Services\CardSettlement\CardSettlementMatcher;
use App\Services\CardSettlement\CardSettlementOrphanRepair;
use App\Services\CardSettlement\CardSettlementRefundReconciler;
use App\Services\CardSettlement\CardSettlementSyncService;
use App\Services\CardSettlement\ParserRegistry;
use App\Services\CardSettlement\TerminalMoveSuggestions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Parses an uploaded card-settlement report (first run) and matches its rows
 * against vend_transactions. Re-dispatching on an already-ingested report
 * (the "Rematch" button — e.g. after adding a missing terminal binding) skips
 * ingestion and only re-runs the matcher over unresolved rows.
 *
 * A Rematch also reaches this report's lines that Sync already turned into NA
 * orphans (CS-<row>): the matcher never re-reads a MATCHED line, so the orphan
 * repair runs the same pairing over them — any whose TRADE has turned up
 * since is replaced by it and its day re-reconciled. One matching logic for
 * upload, Rematch and the nightly repair.
 */
class MatchCardSettlementReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 900;

    public function __construct(public int $reportId)
    {
        $this->onQueue('low');
    }

    public function handle(CardSettlementMatcher $matcher, CardSettlementOrphanRepair $repair, CardSettlementRefundReconciler $reconciler): void
    {
        $report = CardSettlementReport::find($this->reportId);
        if (! $report) {
            return;
        }

        try {
            $report->forceFill([
                'status' => CardSettlementReport::STATUS_MATCHING,
                'error_message' => null,
            ])->save();

            if (! $report->rows()->exists()) {
                $this->ingest($report);
            }

            $matcher->match($report);
            if ($this->autoMove($report)) {
                // Moved terminals: their lines resolve differently now.
                $matcher->match($report);
            }
            $this->repairOrphans($report, $repair, $reconciler);

            // Hands-off: sync as soon as matching is done — query lines stay
            // queries and never block it (config card_settlement.auto_sync).
            if (config('card_settlement.auto_sync')) {
                app(CardSettlementSyncService::class)->sync($report->fresh(), null);
            }
        } catch (Throwable $e) {
            $report->forceFill([
                'status' => CardSettlementReport::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            throw $e;
        }
    }

    /**
     * Apply the terminal moves the evidence makes certain, unattended: at
     * least `auto_move_min_lines` lines on ONE machine that takes this
     * provider, clearly ahead of any other, nothing synced broken, and the
     * machine's current terminal silent there since. Everything weaker stays
     * a suggestion on the page and in the nightly alert email.
     *
     * @return bool whether anything moved
     */
    protected function autoMove(CardSettlementReport $report): bool
    {
        $min = (int) config('card_settlement.auto_move_min_lines', 0);
        if ($min <= 0) {
            return false;
        }
        $suggestions = app(TerminalMoveSuggestions::class);

        $suspects = $suggestions->suspects($report)
            ->filter(fn ($s) => $s['suggested_hits'] >= $min && ($s['would_break_synced'] ?? 0) === 0
                && $this->targetIsSafe($report->provider, (string) $s['suggested_vend_code'], (string) $s['from_date'], (string) $s['terminal_id']))
            ->values();
        [$moved] = $suspects->isNotEmpty() ? $suggestions->applySuspects($suspects) : [[]];

        $unbound = $suggestions->unbound($report)
            ->filter(fn ($t) => $t['suggested_vend_code'] !== null && $t['suggested_hits'] >= $min
                && $this->targetIsSafe($report->provider, (string) $t['suggested_vend_code'], (string) $t['from_date'], (string) $t['terminal_id']))
            ->values();
        [$bound] = $unbound->isNotEmpty() ? $suggestions->applyUnbound($report, $unbound) : [[]];

        foreach (array_merge($moved, $bound) as $what) {
            Log::info('Card settlement auto-move', ['report_id' => $report->id, 'move' => $what]);
        }

        return (bool) ($moved || $bound);
    }

    /** The target takes this provider and its current terminal has not sold there since `$from`. */
    protected function targetIsSafe(string $provider, string $vendCode, string $from, string $terminalId): bool
    {
        $vends = \App\Models\Vend::withoutGlobalScopes()->bareCode($vendCode)->get();
        if ($vends->count() !== 1) {
            return false;
        }
        $vend = $vends->first();
        if ($vend->card_terminal_id && in_array((int) $vend->card_terminal_id, CardSettlementMatcher::foreignCompanyIds($provider ?: 'nets'), true)) {
            return false;
        }
        $current = CardTerminalBinding::query()->where('vend_id', $vend->id)->whereNull('until_at')->where('terminal_id', '!=', $terminalId)->first();
        if (! $current) {
            return true;
        }

        return ! CardSettlementRow::query()
            ->join('vend_transactions as vt', 'vt.id', '=', 'card_settlement_rows.matched_vend_transaction_id')
            ->where('card_settlement_rows.terminal_id', $current->terminal_id)
            ->where('vt.vend_id', $vend->id)
            ->where('vt.is_found_in_transaction', true)
            ->whereRaw('TIMESTAMP(card_settlement_rows.transaction_date, card_settlement_rows.transaction_time) >= ?', [Carbon::parse($from)])
            ->exists();
    }

    protected function repairOrphans(CardSettlementReport $report, CardSettlementOrphanRepair $repair, CardSettlementRefundReconciler $reconciler): void
    {
        $span = $report->rows()->selectRaw('MIN(transaction_date) AS lo, MAX(transaction_date) AS hi')->first();
        if (! $span?->lo) {
            return;
        }

        $days = collect();
        $plan = $repair->plan(Carbon::parse($span->lo)->subDay()->startOfDay(), Carbon::parse($span->hi)->addDay()->endOfDay(), null, $report->id);
        foreach ($plan->filter(fn ($e) => $e['sale'] !== null) as $entry) {
            $days = $days->merge($repair->apply($entry));
        }
        foreach ($days->unique() as $day) {
            $reconciler->reconcileDay(Carbon::parse($day), true);
        }
        if ($days->isNotEmpty()) {
            $report->refreshCounts();
        }
    }

    protected function ingest(CardSettlementReport $report): void
    {
        $attachment = $report->attachment;
        if (! $attachment || blank($attachment->local_url)) {
            throw new \RuntimeException('Report file attachment is missing.');
        }

        // The file lives on the report's private object-storage disk (DO
        // Spaces in prod); the parser wants a local path, so stage a temp copy.
        $tmpPath = tempnam(sys_get_temp_dir(), 'card-settlement-');
        file_put_contents($tmpPath, Storage::disk($report->fileDisk())->get($attachment->local_url));

        try {
            $parsed = ParserRegistry::for($report->provider)->parse($tmpPath);
        } finally {
            @unlink($tmpPath);
        }

        $report->forceFill([
            'merchant_account' => $parsed->merchantAccount,
            'cutover_date' => $parsed->cutoverDate,
            'report_generated_at' => $parsed->reportGeneratedAt,
            'total_rows' => count($parsed->rows),
            'purchase_rows' => collect($parsed->rows)->filter(fn ($r) => $r->isPurchase() && ! $r->isReversal)->count(),
            'reversal_rows' => collect($parsed->rows)->filter(fn ($r) => $r->isReversal)->count(),
            // Lines whose hour was lost to an Excel re-save ("23:12:41" → "12:41.0");
            // they match on mm:ss only — shown as a warning on the report.
            'partial_time_rows' => collect($parsed->rows)->filter(fn ($r) => $r->timeIsPartial)->count(),
        ])->save();

        $now = now();
        foreach (collect($parsed->rows)->chunk(500) as $chunk) {
            $fingerprints = $chunk->map(fn ($r) => CardSettlementRow::fingerprintFor(
                $report->provider, $r->terminalId, $r->transactionDate, $r->sequenceNo, $r->amountCents, $r->transactionTime
            ));

            // A fingerprint seen before means this line was already ingested —
            // the same file re-uploaded, the same day sent again as an
            // Excel-damaged copy (the key ignores the hour precisely so those
            // two spellings collide), or an overlapping cutover window: the
            // NETS business day cuts over inside the 22:00 hour and stragglers
            // settle a day late, so consecutive files can carry one line twice.
            // Keep it for the audit trail, marked DUPLICATE, never matched again.
            $existing = CardSettlementRow::query()
                ->whereIn('fingerprint', $fingerprints)
                ->pluck('fingerprint')
                ->flip();

            $insert = [];
            $seenInChunk = [];
            foreach ($chunk->values() as $i => $row) {
                $fingerprint = $fingerprints->values()[$i];
                $isDuplicate = $existing->has($fingerprint) || isset($seenInChunk[$fingerprint]);
                if ($isDuplicate) {
                    // Same fingerprint twice: keep a distinct one for this
                    // report's copy so the unique index doesn't reject it.
                    $fingerprint = sha1($fingerprint.'|report:'.$report->id.'|row:'.$row->rowNo);
                }
                $seenInChunk[$fingerprint] = true;

                $insert[] = [
                    'card_settlement_report_id' => $report->id,
                    'row_no' => $row->rowNo,
                    'txn_type' => $row->txnType,
                    'product' => $row->product,
                    'card_issuer' => $row->cardIssuer,
                    'terminal_id' => $row->terminalId,
                    'transaction_date' => $row->transactionDate,
                    'transaction_time' => $row->transactionTime,
                    'time_is_partial' => $row->timeIsPartial,
                    'amount_cents' => $row->amountCents,
                    'sequence_no' => $row->sequenceNo,
                    'is_reversal' => $row->isReversal,
                    'fingerprint' => $fingerprint,
                    'status' => $isDuplicate ? CardSettlementRow::STATUS_DUPLICATE : CardSettlementRow::STATUS_PENDING,
                    'resolution_note' => $isDuplicate ? 'Already ingested by an earlier report' : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            CardSettlementRow::insert($insert);
        }
    }
}
