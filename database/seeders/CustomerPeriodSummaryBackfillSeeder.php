<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Services\CustomerSummaryAggregator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Back-fill customer_period_summaries for every month from the earliest
 * customer.begin_date through the current month.
 *
 * Numbers come from gp_metrics. Contract details snapshot uses the customer's
 * CURRENT values (per user direction — historical contract values are not
 * tracked pre-feature; the customer_contract_logs table starts now).
 *
 *   php artisan db:seed --class=CustomerPeriodSummaryBackfillSeeder
 *
 * For very long ranges and large customer bases prefer the artisan command
 * with the --queue mode, which dispatches one job per month:
 *   php artisan customer-summary:compute --since-begin-date
 *
 * This seeder runs synchronously so it can be safely used in a scripted
 * deploy / maintenance window.
 */
class CustomerPeriodSummaryBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $earliest = Customer::query()
            ->withoutGlobalScopes()
            ->whereNotNull('begin_date')
            ->min('begin_date');

        if (!$earliest) {
            $this->command?->warn('No customers with begin_date — nothing to backfill.');
            return;
        }

        // Floor to gp_metrics earliest available date — there's no point
        // creating rows for months before any transactions ever existed.
        $gpMetricsEarliest = DB::table('gp_metrics')->min('txn_date');
        $rangeStart = Carbon::parse($earliest)->startOfMonth();
        if ($gpMetricsEarliest) {
            $gpStartMonth = Carbon::parse($gpMetricsEarliest)->startOfMonth();
            if ($gpStartMonth->gt($rangeStart)) {
                $rangeStart = $gpStartMonth;
            }
        }

        $rangeEnd = Carbon::today()->startOfMonth();
        $asOf = Carbon::today()->subDay();

        $this->command?->info(sprintf(
            'CustomerPeriodSummaryBackfillSeeder: %s → %s (as_of=%s)',
            $rangeStart->format('Y-m'),
            $rangeEnd->format('Y-m'),
            $asOf->toDateString()
        ));

        $cursor = $rangeStart->copy();
        $totalRows = 0;
        while ($cursor->lte($rangeEnd)) {
            $count = CustomerSummaryAggregator::persistMonth($cursor->copy(), $asOf);
            $totalRows += $count;
            $this->command?->info(sprintf(' - %s: %d rows', $cursor->format('Y-m'), $count));
            $cursor->addMonthNoOverflow();
        }

        $this->command?->info(sprintf('CustomerPeriodSummaryBackfillSeeder: done, %d rows total.', $totalRows));
    }
}
