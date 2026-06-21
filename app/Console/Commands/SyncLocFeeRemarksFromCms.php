<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\CmsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Auto-fills the Site Summary "Remarks for Loc Fees" field
 * (customers.loc_fee_remarks) from CMS outstanding-invoice data.
 *
 * For every Active site that is linked to a CMS person (customers.person_id),
 * we ask CMS for that person's "Owe" summary and write a note shaped like:
 *
 *     since 260131, owe $2,700
 *
 * where the date is the OLDEST outstanding invoice's delivery date (yymmdd)
 * and the amount is the CMS "Total Owe" aggregate for that person.
 *
 * Safe by design:
 *  - Dry-run by default; prints a preview table and writes nothing unless
 *    --apply is passed.
 *  - Writes via a bounded query-builder update on two columns only
 *    (loc_fee_remarks + loc_fee_remarks_updated_at), so no model events,
 *    observers, or CMS sync side-effects fire.
 *  - Sites with no outstanding owe are skipped (left untouched) unless
 *    --clear-when-no-owe is passed.
 */
class SyncLocFeeRemarksFromCms extends Command
{
    protected $signature = 'cms:sync-loc-fee-remarks
        {--apply : Persist changes. Without this flag the command only previews.}
        {--customer= : Limit to a single customer id (for testing).}
        {--limit= : Process at most N customers.}
        {--clear-when-no-owe : Blank out loc_fee_remarks for sites with zero owe (default: leave untouched).}';

    protected $description = 'Fill Site Summary "Remarks for Loc Fees" from CMS outstanding-invoice (Owe) data for Active, CMS-linked sites.';

    public function handle(CmsService $cms): int
    {
        $apply = (bool) $this->option('apply');
        $clearWhenNoOwe = (bool) $this->option('clear-when-no-owe');

        if (!config('app.cms_url')) {
            $this->error('CMS_URL is not configured; nothing to do.');
            return self::FAILURE;
        }

        $query = Customer::query()
            ->where('status_id', Customer::STATUS_ACTIVE)
            ->whereNotNull('person_id')
            ->orderBy('person_id');

        if ($this->option('customer')) {
            $query->where('id', (int) $this->option('customer'));
        }
        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $customers = $query->get(['id', 'name', 'company_remark', 'person_id', 'loc_fee_remarks']);

        if ($customers->isEmpty()) {
            $this->warn('No Active, CMS-linked customers matched.');
            return self::SUCCESS;
        }

        $this->info(($apply ? 'APPLY' : 'DRY-RUN') . ' — ' . $customers->count() . ' Active CMS-linked site(s).');
        if (!$apply) {
            $this->comment('No changes will be written. Re-run with --apply to persist.');
        }

        $rows = [];
        $written = 0;
        $skippedNoOwe = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($customers->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — person_id %message%');
        $bar->setMessage('—');
        $bar->start();

        foreach ($customers as $c) {
            $bar->setMessage((string) $c->person_id);
            $bar->advance();

            $summary = $cms->getPersonOweSummary($c->person_id);

            $label = $this->shortName($c->name ?: $c->company_remark);

            if ($summary === null) {
                $failed++;
                $rows[] = [$c->id, $label, $c->person_id, '—', '—', 'CMS FETCH FAILED'];
                continue;
            }

            $totalOwe = (float) ($summary['total_owe'] ?? 0);
            $oldest = $summary['oldest_owe_date'] ?? null;

            // No outstanding balance (or no dated owe invoice) => nothing meaningful to write.
            if ($totalOwe <= 0 || empty($oldest)) {
                $skippedNoOwe++;
                $action = 'skip (no owe)';

                if ($clearWhenNoOwe && $c->loc_fee_remarks !== null && $c->loc_fee_remarks !== '') {
                    $action = 'CLEAR';
                    if ($apply) {
                        $this->persist($c->id, null);
                        $written++;
                    }
                }

                $rows[] = [$c->id, $label, $c->person_id, $oldest ?: '—', $this->money($totalOwe), $action];
                continue;
            }

            $remark = 'since ' . Carbon::parse($oldest)->format('ymd') . ', owe $' . $this->money($totalOwe);

            $action = ($c->loc_fee_remarks === $remark) ? 'unchanged' : 'WRITE';
            if ($apply && $action === 'WRITE') {
                $this->persist($c->id, $remark);
                $written++;
            }

            $rows[] = [$c->id, $label, $c->person_id, Carbon::parse($oldest)->format('ymd'), $this->money($totalOwe), $action . ' → "' . $remark . '"'];
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Cust ID', 'Site', 'person_id', 'Oldest (ymd)', 'Total Owe', 'Action / Remark'],
            $rows
        );

        $this->info(sprintf(
            '%s: %d written, %d skipped (no owe), %d CMS fetch failure(s).',
            $apply ? 'Applied' : 'Preview',
            $written,
            $skippedNoOwe,
            $failed
        ));

        if (!$apply && ($written === 0)) {
            $writeCandidates = collect($rows)->filter(fn ($r) => str_starts_with($r[5], 'WRITE') || $r[5] === 'CLEAR')->count();
            if ($writeCandidates > 0) {
                $this->comment($writeCandidates . ' site(s) would change. Re-run with --apply to persist.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Bounded write: only the two loc-fee-remarks columns, via query builder
     * so no Customer model events / observers fire.
     */
    private function persist(int $customerId, ?string $remark): void
    {
        Customer::where('id', $customerId)->update([
            'loc_fee_remarks' => $remark,
            'loc_fee_remarks_updated_at' => now(),
        ]);
    }

    /** Format money like the sample: "2,700" whole, "2,700.50" with cents. */
    private function money(float $amount): string
    {
        return (fmod($amount, 1.0) === 0.0)
            ? number_format($amount, 0)
            : number_format($amount, 2);
    }

    private function shortName(?string $name): string
    {
        $name = (string) $name;
        return mb_strlen($name) > 28 ? mb_substr($name, 0, 27) . '…' : $name;
    }
}
