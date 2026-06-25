<?php

namespace App\Console\Commands;

use App\Models\CustomerPeriodSummary;
use App\Models\CustomerSettlement;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Accrue each site's monthly location fee into the settlement ledger
 * (Site Summary ▸ Payment History) as a CHARGE we owe the site owner.
 *
 * Runs on the 1st of each month for the PREVIOUS month (the month is fully
 * closed by then and the nightly customer-summary:compute at 01:00 has settled
 * its figures). For each site whose Net Loc Fee for the month is POSITIVE
 * (we owe them), it posts ONE location_fee row dated the last day of that
 * month. Free / Subsidized / zero / negative (we-receive) sites are skipped.
 *
 *   Net Loc Fee = location_fees_cents − external_subsidize_cents
 *                 (summed across all summary rows for that customer + month,
 *                  so mid-month machine-split segments roll up correctly)
 *
 * Idempotent: a customer that already has a location_fee row for the month is
 * skipped, so re-runs (or the May back-seed) never double-post. APPLIES by
 * default (it's a cron); pass --dry-run to preview.
 *
 * Examples:
 *   php artisan settlement:generate-monthly                 # previous month
 *   php artisan settlement:generate-monthly --month=2026-05 # specific month
 *   php artisan settlement:generate-monthly --dry-run
 */
class GenerateMonthlySettlements extends Command
{
    protected $signature = 'settlement:generate-monthly
        {--month= : Target month as YYYY-MM (or any date in it). Default: previous month.}
        {--customer= : Limit to a single customer id (for testing).}
        {--source=cron : Provenance tag stored on created rows (cron|seed|manual).}
        {--dry-run : Preview only; write nothing.}';

    protected $description = 'Post each owing site\'s monthly Net Loc Fee into the settlement ledger as a charge.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $source = (string) ($this->option('source') ?: CustomerSettlement::SOURCE_CRON);

        // Resolve target month → first day (matches year_month) + last day.
        $monthStart = $this->option('month')
            ? Carbon::parse($this->normaliseMonth($this->option('month')))->startOfMonth()
            : Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $ymDate = $monthStart->toDateString();

        $this->info(sprintf(
            '%s — settlement accrual for %s (charge dated %s).',
            $dryRun ? 'DRY-RUN' : 'APPLY',
            $monthStart->format('F Y'),
            $monthEnd->toDateString()
        ));

        // Roll up Net Loc Fee per customer for the month. Summing across rows
        // handles machine-split segments; operator_id is taken via MAX (it's
        // constant per customer within a month).
        $rows = CustomerPeriodSummary::query()
            ->where('year_month', $ymDate)
            ->when($this->option('customer'), fn ($q) => $q->where('customer_id', (int) $this->option('customer')))
            ->groupBy('customer_id')
            ->selectRaw('customer_id')
            ->selectRaw('MAX(operator_id) AS operator_id')
            ->selectRaw('SUM(location_fees_cents) - SUM(external_subsidize_cents) AS net_loc_fee_cents')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No period summaries found for ' . $monthStart->format('F Y') . '. Nothing to do.');
            return self::SUCCESS;
        }

        // Existing location_fee rows for this month → skip set (idempotency).
        $already = CustomerSettlement::query()
            ->where('entry_type', CustomerSettlement::TYPE_LOCATION_FEE)
            ->where('year_month', $ymDate)
            ->pluck('customer_id')
            ->flip();

        $itemLabel = 'Location Fees ' . $monthStart->format('F Y');

        $posted = 0;
        $skippedNoOwe = 0;
        $skippedExisting = 0;
        $totalCents = 0;

        foreach ($rows as $r) {
            $net = (int) $r->net_loc_fee_cents;

            if ($net <= 0) {            // Free / Subsidized / zero / we-receive.
                $skippedNoOwe++;
                continue;
            }
            if ($already->has($r->customer_id)) {
                $skippedExisting++;
                continue;
            }

            $totalCents += $net;
            $posted++;

            if ($dryRun) {
                continue;
            }

            CustomerSettlement::create([
                'customer_id'  => $r->customer_id,
                'operator_id'  => $r->operator_id,
                'entry_date'   => $monthEnd->toDateString(),
                'year_month'   => $ymDate,
                'entry_type'   => CustomerSettlement::TYPE_LOCATION_FEE,
                'amount_cents' => $net,           // +ve charge — increases owed.
                'item'         => $itemLabel,
                'remarks'      => null,
                'source'       => $source,
                'created_by'   => null,           // system-generated.
            ]);
        }

        $this->info(sprintf(
            '%s%d charge(s) posted totalling %s. Skipped: %d no-owe (F/S/zero/negative), %d already present.',
            $dryRun ? '(dry-run) would post ' : '',
            $posted,
            $this->money($totalCents),
            $skippedNoOwe,
            $skippedExisting
        ));

        return self::SUCCESS;
    }

    /** Accept "2026-05", "2026-05-01", or any date; normalise to a parseable date. */
    private function normaliseMonth(string $month): string
    {
        $month = trim($month);
        return preg_match('/^\d{4}-\d{2}$/', $month) ? ($month . '-01') : $month;
    }

    private function money(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
}
