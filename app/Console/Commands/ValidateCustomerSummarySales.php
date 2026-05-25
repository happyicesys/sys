<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerPeriodSummary;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sanity-check tool — confirms customer_period_summaries.sales_cents
 * matches the live "Total Sales" the Transactions page would show for
 * the same window.
 *
 * The two should agree because Customer Summary now sources sales_cents
 * directly from vend_transactions.amount (incl-GST) using the same filter
 * the Transactions page applies — see CustomerSummaryAggregator::persistMonth.
 * Use this command after any backfill or aggregator change to catch drift.
 *
 * Filter parity with /vends/transactions:
 *   - vend_channel_errors.code IN (0, 6) OR code IS NULL OR is_multiple = true
 *   - testing vends excluded
 *   - vend_transactions.amount is the source of truth (incl-GST)
 *
 * Usage:
 *   php artisan customer-summary:validate-sales
 *   php artisan customer-summary:validate-sales --month=2026-04
 *   php artisan customer-summary:validate-sales --month=2026-05 --customer-id=6547
 *   php artisan customer-summary:validate-sales --month=2026-05 --vend-code=4612
 *   php artisan customer-summary:validate-sales --month=2026-04 --limit=20 --tolerance=200
 *
 * Exit code:
 *   0 → all rows within tolerance
 *   1 → mismatch found (CI-friendly)
 */
class ValidateCustomerSummarySales extends Command
{
    protected $signature = 'customer-summary:validate-sales
        {--month= : Target month (YYYY-MM). Defaults to last completed month.}
        {--customer-id= : Validate one customer only.}
        {--vend-code= : Validate customers bound to this machine code.}
        {--limit=50 : Max customers to validate (default 50).}
        {--tolerance=100 : Acceptable cents diff per row (default 100 = $1, covers rounding noise).}
        {--all : Validate every customer with a summary row in the month, ignoring --limit.}
        {--show-matches : Print matched rows too (default: mismatches only).}';

    protected $description = 'Compare customer_period_summaries.sales_cents against the live Transactions-page total for each customer';

    public function handle(): int
    {
        // ── Resolve the month window ────────────────────────────────────
        $month = $this->option('month')
            ? Carbon::parse($this->option('month'))->startOfMonth()
            : Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth()->endOfDay();

        // For the current (in-progress) month, cap the live transactions
        // window at the summaries' as_of_date — otherwise the live query
        // sees today's transactions that the summaries haven't aggregated
        // yet (the nightly aggregator runs at ~01:00 for yesterday) and
        // we'd flag every row as a "mismatch".
        $isCurrentMonth = $monthStart->isSameMonth(Carbon::today());

        $tolerance = (int) $this->option('tolerance');
        $tolerance = $tolerance < 0 ? 0 : $tolerance;

        // ── Build the customer set we'll validate ───────────────────────
        $summariesQuery = CustomerPeriodSummary::query()
            ->whereBetween('year_month', [$monthStart->toDateString(), $monthEnd->toDateString()]);

        if ($this->option('customer-id')) {
            $summariesQuery->where('customer_id', (int) $this->option('customer-id'));
        }

        if ($this->option('vend-code')) {
            $vendCustomerIds = DB::table('vends')
                ->where('code', 'LIKE', $this->option('vend-code') . '%')
                ->whereNotNull('customer_id')
                ->pluck('customer_id');
            $summariesQuery->whereIn('customer_id', $vendCustomerIds);
        }

        if (!$this->option('all')) {
            $summariesQuery->limit((int) $this->option('limit'));
        }

        $summaries = $summariesQuery
            ->orderBy('customer_id')
            ->get(['id', 'customer_id', 'year_month', 'period_start', 'period_end', 'sales_cents', 'as_of_date'])
            ->keyBy('customer_id');

        if ($summaries->isEmpty()) {
            $this->warn('No customer_period_summaries rows match the filter — nothing to validate.');
            $this->line('Try a different --month, or run `customer-summary:compute --month=' . $monthStart->format('Y-m') . '` first.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Validating %d customer(s) for %s (tolerance: %d cents = $%.2f)%s',
            $summaries->count(),
            $monthStart->format('Y-m'),
            $tolerance,
            $tolerance / 100,
            $isCurrentMonth ? ' [current month — live query capped at each row\'s as_of_date]' : ''
        ));

        // ── Pre-fetch testing vend ids (same gating the Transactions
        //    page uses — Cache::remember keeps us cheap when the page
        //    has just been visited).
        $testingVendIds = Cache::remember('testing_vend_ids', 3600, fn () =>
            DB::table('vends')->where('is_testing', true)->pluck('id')->map(fn ($v) => (int) $v)->all()
        );

        // ── Per-customer comparison ──────────────────────────────────────
        $rows = [];
        $matchCount = 0;
        $mismatchCount = 0;
        $largestDiff = 0;

        foreach ($summaries as $customerId => $summary) {
            $customer = Customer::withoutGlobalScopes()->find($customerId);
            $customerName = $customer ? $customer->name : '#' . $customerId;

            // Per-row date window — for current-month rows, cap at the
            // summary's as_of_date so live and stored cover the same days.
            $rowEnd = $isCurrentMonth && $summary->as_of_date
                ? Carbon::parse($summary->as_of_date)->endOfDay()
                : $monthEnd->copy();

            $liveAmountCents = $this->liveTransactionsAmountCents(
                $customerId,
                $monthStart,
                $rowEnd,
                $testingVendIds
            );

            $summaryCents = (int) $summary->sales_cents;
            $diff = $summaryCents - $liveAmountCents;
            $absDiff = abs($diff);
            $isMatch = $absDiff <= $tolerance;

            if ($absDiff > $largestDiff) {
                $largestDiff = $absDiff;
            }

            if ($isMatch) {
                $matchCount++;
            } else {
                $mismatchCount++;
            }

            if (!$isMatch || $this->option('show-matches')) {
                $rows[] = [
                    'id' => $customerId,
                    'name' => mb_strimwidth((string) $customerName, 0, 30, '…'),
                    'summary_$' => number_format($summaryCents / 100, 2),
                    'live_$' => number_format($liveAmountCents / 100, 2),
                    'diff_$' => number_format($diff / 100, 2),
                    'status' => $isMatch ? 'OK' : 'MISMATCH',
                ];
            }
        }

        // ── Output ───────────────────────────────────────────────────────
        if (!empty($rows)) {
            $this->table(
                ['Customer ID', 'Name', 'Summary $', 'Live $', 'Diff (S - L) $', 'Status'],
                $rows
            );
        } else {
            $this->info('All rows matched within tolerance.');
        }

        $this->newLine();
        $this->info(sprintf(
            'Checked: %d | Matched: %d | Mismatched: %d | Largest abs diff: $%.2f',
            $summaries->count(),
            $matchCount,
            $mismatchCount,
            $largestDiff / 100
        ));

        if ($mismatchCount > 0) {
            $this->warn('Mismatch hint: positive diff = summary higher than live; negative = lower.');
            $this->line('Common causes after the cent-exact rewire (sales_cents now from vend_transactions):');
            $this->line('  - Aggregator hasn\'t been re-run since the change. Re-aggregate the affected months:');
            $this->line('    php artisan customer-summary:compute --month=' . $monthStart->format('Y-m'));
            $this->line('  - Live transactions cover a different date range (check period_end / as_of_date).');
            $this->line('  - New vend_transactions rows arrived AFTER the nightly aggregator ran — wait for the next');
            $this->line('    nightly recompute or re-run the command above for the affected month.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Sum vend_transactions.amount for a customer over the window using
     * the SAME filter the Transactions page applies:
     *   - successful txns (error_code IN (0, 6) OR NULL) OR is_multiple = true
     *   - testing vends excluded
     *   - transaction_datetime (fallback to created_at) in [start, end]
     */
    protected function liveTransactionsAmountCents(
        int $customerId,
        Carbon $start,
        Carbon $end,
        array $testingVendIds
    ): int {
        $startStr = $start->copy()->startOfDay();
        $endStr = $end->copy()->endOfDay();

        $sum = DB::table('vend_transactions')
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->where('vend_transactions.customer_id', $customerId)
            ->where(function ($q) use ($startStr, $endStr) {
                $q->whereBetween('vend_transactions.transaction_datetime', [$startStr, $endStr])
                  ->orWhere(function ($or) use ($startStr, $endStr) {
                      $or->whereNull('vend_transactions.transaction_datetime')
                         ->whereBetween('vend_transactions.created_at', [$startStr, $endStr]);
                  });
            })
            ->when(!empty($testingVendIds), fn ($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
            // Unified transactions: mirror CustomerSummaryAggregator — only SETTLED
            // rows count (no-op for legacy/non-gateway rows).
            ->where('vend_transactions.settlement_status', \App\Models\VendTransaction::SETTLEMENT_SETTLED)
            ->where(function ($q) {
                // Mirror the Transactions page success_amount filter exactly:
                // - error code 0 / 6 / NULL OR
                // - is_multiple = true (treats all multi-purchase as success
                //   at the txn level; per-item filtering happens elsewhere).
                $q->whereIn('vend_channel_errors.code', [0, 6])
                  ->orWhereNull('vend_channel_errors.code')
                  ->orWhere('vend_transactions.is_multiple', true);
            })
            ->sum('vend_transactions.amount');

        return (int) round((float) $sum);
    }
}
