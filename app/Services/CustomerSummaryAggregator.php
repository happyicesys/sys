<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPeriodSummary;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aggregates per-customer per-month numbers used by the
 * Customer Management > Summary page.
 *
 * Data sources, by column:
 *   sales_cents          ← vend_transactions (cent-exact, source of truth;
 *                          mirrors the /vends/transactions success_amount
 *                          filter so Customer Summary matches the
 *                          Transactions page exactly)
 *   gross_earning_cents  ← gp_metrics.gross_profit_cents (per-product cost
 *                          accounting only available in the rollup)
 *   transaction_count    ← gp_metrics.transaction_count
 *   vend_count           ← gp_metrics distinct vend_id
 *
 * Why sales_cents bypasses gp_metrics: GpMetricsAggregator splits multi-
 * purchase basket totals across products via decimal division, then GROUPs
 * BY product. Sub-cent fractions are lost when stored back into the INT
 * amount_cents column, accumulating into off-by-cents drift on the Summary
 * page (e.g. 514.59 vs Transactions' 514.60). Reading vend_transactions
 * directly for the Sales column eliminates that drift.
 *

 * Sign convention for location_fees:
 *   positive = expense (we pay the location)
 *   negative = income  (location pays us, e.g. Subsidized Plan)
 *
 * Location Fee formulas — mirror Customer/Edit.vue's 7 contract types.
 * PS-family formulas de-gross sales by (1 + operator GST%) before
 * applying PS Term so the stored Location Fees agree with the
 * Performance Report Preview popup (PerformanceReportContentService).
 * "sales(excl)" below = sales_cents / (1 + gst_rate%); "sales" with no
 * suffix preserves the old gst=0 behaviour for the default call signature.
 *   F      Free Placement              0
 *   S      Subsidized Plan             -value          (we receive)
 *   R      Fix Rental                  +value
 *   U      Utility only                +value
 *   PS     Profit Sharing              sales(excl) * ps_term% * value%
 *   PS+U   PS + Utility                sales(excl) * ps_term% * value% + value2
 *   PSORU  PS or Utility (whichever)   max(PS_amount, value2)
 *
 * Verified against screenshots:
 *   PS+U value=30 value2=50 ps_term=70 sales=$1000 (gst=0)
 *   => 1000 * 0.70 * 0.30 + 50 = $260  ✓
 *
 *   PS+U value=40 value2=50 ps_term=70 sales=$733.40 gst=9
 *   => 733.40/1.09 * 0.70 * 0.40 + 50 = $238.39  ✓
 */
class CustomerSummaryAggregator
{
    public const CONTRACT_TYPE_FREE = 'F';
    public const CONTRACT_TYPE_SUBSIDIZED = 'S';
    public const CONTRACT_TYPE_RENTAL = 'R';
    public const CONTRACT_TYPE_UTILITY = 'U';
    public const CONTRACT_TYPE_PS = 'PS';
    public const CONTRACT_TYPE_PS_U = 'PS+U';
    public const CONTRACT_TYPE_PS_OR_U = 'PSORU';

    /**
     * Compute Location Fees in the same currency unit as $salesCents / $grossEarningCents
     * (i.e. minor units / cents). Inputs that are decimals (value, value2, ps_term)
     * are in the customer-facing units (e.g. value=30 means 30%, value=300 means $300).
     *
     * $gstRatePct is the operator's GST/VAT rate in percent (e.g. 9 for 9%). PS-family
     * formulas de-gross the INCL-GST $salesCents by (1 + gstRatePct%) before applying
     * PS Term, so the stored Location Fees match the Performance Report Preview popup
     * (PerformanceReportContentService::generate()). Defaults to 0 — i.e. the legacy
     * behaviour — so any legacy caller that hasn't been threaded through still works.
     *
     * Returns an integer cent value with the sign convention above.
     */
    public static function computeLocationFeeCents(
        ?string $contractType,
        ?float $value,
        ?float $value2,
        ?float $psTerm,
        int $salesCents,
        int $grossEarningCents,
        float $gstRatePct = 0.0
    ): int {
        if ($contractType === null) {
            return 0;
        }

        $value = $value !== null ? (float) $value : 0.0;
        $value2 = $value2 !== null ? (float) $value2 : 0.0;
        $psTerm = $psTerm !== null ? (float) $psTerm : 0.0;

        switch ($contractType) {
            case self::CONTRACT_TYPE_FREE:
                return 0;

            case self::CONTRACT_TYPE_SUBSIDIZED:
                // Subsidy is INCOME from the location (negative location fee).
                // value is in dollars → convert to cents.
                return -1 * (int) round($value * 100);

            case self::CONTRACT_TYPE_RENTAL:
            case self::CONTRACT_TYPE_UTILITY:
                // Flat amount in dollars per period
                return (int) round($value * 100);

            case self::CONTRACT_TYPE_PS:
                return self::psAmountCents($salesCents, $psTerm, $value, $gstRatePct);

            case self::CONTRACT_TYPE_PS_U:
                return self::psAmountCents($salesCents, $psTerm, $value, $gstRatePct)
                    + (int) round($value2 * 100);

            case self::CONTRACT_TYPE_PS_OR_U:
                return max(
                    self::psAmountCents($salesCents, $psTerm, $value, $gstRatePct),
                    (int) round($value2 * 100)
                );
        }

        return 0;
    }

    private static function psAmountCents(
        int $salesCents,
        float $psTerm,
        float $commissionPercent,
        float $gstRatePct = 0.0
    ): int {
        if ($salesCents <= 0) {
            return 0;
        }
        // $salesCents is INCL-GST (sourced from vend_transactions.amount).
        // De-gross by (1 + gst%) so the PS base matches the EXCL-GST figure
        // shown in the Performance Report Preview popup.
        $gstDivisor = 1 + ($gstRatePct / 100.0);
        $exclGstSalesCents = $gstDivisor > 0
            ? $salesCents / $gstDivisor
            : $salesCents;

        // sales(excl-gst) * ps_term% * commission%
        $amount = $exclGstSalesCents * ($psTerm / 100.0) * ($commissionPercent / 100.0);
        return (int) round($amount);
    }

    /**
     * Recompute and persist (upsert) summary rows for every customer for the
     * month containing $reference.
     *
     * - Past months: fully aggregated from gp_metrics for the whole month;
     *   period_end = last day of month, is_current_month = false.
     * - Current month: aggregated up to and including $asOf (typically yesterday);
     *   period_end = $asOf, is_current_month = true.
     *
     * Idempotent — safe to run multiple times for the same month.
     */
    public static function persistMonth(CarbonInterface $reference, ?CarbonInterface $asOf = null): int
    {
        $monthStart = Carbon::parse($reference)->startOfMonth();
        $monthEndCalendar = Carbon::parse($reference)->endOfMonth()->startOfDay();
        $today = Carbon::today();
        $asOf = $asOf ? Carbon::parse($asOf)->startOfDay() : $today->copy()->subDay();

        // The current in-progress month: cap period_end at "yesterday".
        $isCurrentMonth = $monthStart->isSameMonth($today);
        $periodEnd = $isCurrentMonth
            ? $asOf->copy()
            : $monthEndCalendar->copy();

        // Defensive: if asOf is before the month started (e.g. running for a
        // future month), there's nothing to aggregate.
        if ($periodEnd->lt($monthStart)) {
            return 0;
        }

        // Pull aggregated numbers from gp_metrics for the whole month window.
        // gp_metrics.txn_date is a DATE column.
        //
        // gross_earning_cents ← gross_profit_cents (revenue - unit_cost,
        //                       still excl-GST). The "Gross Earning (excl GST)"
        //                       column header is explicit about this.
        // transaction_count / vend_count are pure counts so no rounding noise.
        $rows = DB::table('gp_metrics')
            ->whereBetween('txn_date', [$monthStart->toDateString(), $periodEnd->toDateString()])
            ->whereNotNull('customer_id')
            ->select('customer_id')
            ->selectRaw('SUM(gross_profit_cents) AS gross_earning_cents')
            ->selectRaw('SUM(transaction_count) AS transaction_count')
            ->selectRaw('COUNT(DISTINCT vend_id) AS vend_count')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        // sales_cents must be sourced from vend_transactions DIRECTLY — the
        // Transactions page is the source of truth for "Total Sales" and
        // users need the two screens to match to the cent.
        //
        // Why not gp_metrics.amount_cents (the previous source)? For
        // multi-purchase baskets, GpMetricsAggregator splits the basket
        // total across products via `(amount - item_sum) / item_count` and
        // then GROUPs BY product. The decimal division is mathematically
        // exact in aggregate but loses sub-cent fractions when each row is
        // stored back into the INT amount_cents column. SUM-ming many such
        // rows for one customer accumulates the loss and produces the
        // off-by-cents drift users were reporting (e.g. 514.59 vs 514.60).
        //
        // Filter parity with /vends/transactions success_amount:
        //   - vend_channel_errors.code IN (0, 6) OR code IS NULL OR is_multiple = true
        //   - testing vends excluded
        //   - transaction_datetime (fallback to created_at) inside [monthStart, periodEnd]
        //
        // Used by PerformanceReportContentService / CustomerInvoiceService as
        // the base for PS-family invoice math (PS amount = sales × ps_term%
        // × commission%). Re-run the aggregator after deploy:
        //   php artisan customer-summary:compute --since-begin-date
        // so historical rows pick up the corrected (cent-exact) values.
        $testingVendIds = Cache::remember('testing_vend_ids', 3600, fn () =>
            DB::table('vends')->where('is_testing', true)->pluck('id')->map(fn ($v) => (int) $v)->all()
        );

        $windowStart = $monthStart->copy()->startOfDay();
        $windowEnd = $periodEnd->copy()->endOfDay();

        $salesByCustomer = DB::table('vend_transactions')
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->whereNotNull('vend_transactions.customer_id')
            ->where(function ($q) use ($windowStart, $windowEnd) {
                $q->whereBetween('vend_transactions.transaction_datetime', [$windowStart, $windowEnd])
                  ->orWhere(function ($or) use ($windowStart, $windowEnd) {
                      $or->whereNull('vend_transactions.transaction_datetime')
                         ->whereBetween('vend_transactions.created_at', [$windowStart, $windowEnd]);
                  });
            })
            ->where(function ($q) {
                // Mirror the Transactions page success_amount filter exactly:
                // - error code 0 / 6 / NULL OR
                // - is_multiple = true (treats every multi-purchase txn as
                //   success at the basket level; per-item filtering is a
                //   separate concern).
                $q->whereIn('vend_channel_errors.code', [0, 6])
                  ->orWhereNull('vend_channel_errors.code')
                  ->orWhere('vend_transactions.is_multiple', true);
            })
            ->when(!empty($testingVendIds), fn ($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
            // Unified transactions: exclude in-flight (PENDING) and voided
            // (REFUNDED) gateway rows from billed sales. Legacy + non-gateway rows
            // are SETTLED (column default) → no change to existing invoices.
            ->where('vend_transactions.settlement_status', \App\Models\VendTransaction::SETTLEMENT_SETTLED)
            ->groupBy('vend_transactions.customer_id')
            ->selectRaw('vend_transactions.customer_id AS customer_id, SUM(vend_transactions.amount) AS sales_cents')
            ->pluck('sales_cents', 'customer_id');

        // We also need to emit "zero" rows for active customers that had no
        // transactions in the window — otherwise they'd disappear from the
        // Summary page even though their contract fees still apply (e.g. fix
        // rental we still pay). Keep this scoped to customers whose begin_date
        // is on/before the period_end and (if terminated) termination_date is
        // on/after period_start.
        $customerQuery = Customer::query()
            ->withoutGlobalScopes() // aggregator runs system-wide
            ->where(function ($q) use ($periodEnd) {
                $q->whereNull('begin_date')->orWhere('begin_date', '<=', $periodEnd->copy()->endOfDay());
            })
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('termination_date')->orWhere('termination_date', '>=', $monthStart);
            });

        // Pre-load operator GST rates as a single small lookup so the
        // per-customer chunk loop can resolve operator->gst_vat_rate in O(1)
        // without an N+1 of `Customer::with('operator')` for every row.
        $operatorGstRates = DB::table('operators')
            ->pluck('gst_vat_rate', 'id')
            ->map(fn ($v) => (float) $v)
            ->all();

        $now = now();
        $payloads = [];
        $customerQuery->select([
            'id',
            'operator_id',
            'contract_commission_type',
            'contract_commission_value',
            'contract_commission_value2',
            'contract_ps_term',
        ])->chunk(500, function ($customers) use ($rows, $salesByCustomer, $monthStart, $periodEnd, $isCurrentMonth, $asOf, $monthEndCalendar, $now, $operatorGstRates, &$payloads) {
            foreach ($customers as $customer) {
                $row = $rows->get($customer->id);
                // sales_cents: from vend_transactions (cent-exact, matches
                // Transactions page). Other metrics from gp_metrics rollup.
                $salesCents = (int) round((float) ($salesByCustomer[$customer->id] ?? 0));
                $grossEarningCents = (int) ($row->gross_earning_cents ?? 0);
                $transactionCount = (int) ($row->transaction_count ?? 0);
                $vendCount = (int) ($row->vend_count ?? 0);

                // Operator GST rate (e.g. 9.00 for 9%). Used by PS-family
                // formulas to de-gross the INCL-GST sales basis before
                // applying PS Term — matches the popup's math.
                $gstRatePct = (float) ($operatorGstRates[$customer->operator_id] ?? 0);

                $locationFeeCents = self::computeLocationFeeCents(
                    $customer->contract_commission_type,
                    $customer->contract_commission_value !== null ? (float) $customer->contract_commission_value : null,
                    $customer->contract_commission_value2 !== null ? (float) $customer->contract_commission_value2 : null,
                    $customer->contract_ps_term !== null ? (float) $customer->contract_ps_term : null,
                    $salesCents,
                    $grossEarningCents,
                    $gstRatePct
                );

                $locationEarningCents = $grossEarningCents - $locationFeeCents;
                $locationEarningRate = $salesCents > 0
                    ? round($locationEarningCents / $salesCents, 4)
                    : 0;

                // Skip rows that have nothing useful to show: no sales AND no
                // contract fee. Keeps the table compact for very long-tail
                // customers that didn't trade in the window.
                if ($salesCents === 0 && $locationFeeCents === 0 && $transactionCount === 0) {
                    continue;
                }

                $payloads[] = [
                    'customer_id' => $customer->id,
                    'operator_id' => $customer->operator_id,
                    'year_month' => $monthStart->toDateString(),
                    'period_start' => $monthStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'is_current_month' => $isCurrentMonth,
                    'as_of_date' => ($isCurrentMonth ? $asOf : $monthEndCalendar)->toDateString(),
                    'sales_cents' => $salesCents,
                    'gross_earning_cents' => $grossEarningCents,
                    'location_fees_cents' => $locationFeeCents,
                    'location_earning_cents' => $locationEarningCents,
                    'location_earning_rate' => $locationEarningRate,
                    'transaction_count' => $transactionCount,
                    'vend_count' => $vendCount,
                    'contract_commission_type' => $customer->contract_commission_type,
                    'contract_commission_value' => $customer->contract_commission_value,
                    'contract_commission_value2' => $customer->contract_commission_value2,
                    'contract_ps_term' => $customer->contract_ps_term,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        });

        if (empty($payloads)) {
            // Still wipe any stale rows for this month that no longer qualify.
            CustomerPeriodSummary::query()
                ->where('year_month', $monthStart->toDateString())
                ->delete();
            return 0;
        }

        // Idempotent overwrite for this month.
        DB::transaction(function () use ($payloads, $monthStart) {
            CustomerPeriodSummary::query()
                ->where('year_month', $monthStart->toDateString())
                ->delete();

            foreach (array_chunk($payloads, 500) as $chunk) {
                CustomerPeriodSummary::query()->insert($chunk);
            }
        });

        Log::info('CustomerSummaryAggregator: persisted month', [
            'year_month' => $monthStart->toDateString(),
            'rows' => count($payloads),
            'is_current_month' => $isCurrentMonth,
            'as_of_date' => $asOf->toDateString(),
        ]);

        return count($payloads);
    }

    /**
     * Convenience: recompute the month containing yesterday.
     *
     * If yesterday was the last day of the previous month, we additionally
     * recompute that just-finished month so it gets its final period_end stamp.
     */
    public static function persistDailyDefault(): array
    {
        $yesterday = Carbon::today()->subDay();
        $months = [$yesterday->copy()->startOfMonth()];

        // If today is the 1st, yesterday closed out the previous month.
        if (Carbon::today()->day === 1) {
            // Already covered above (yesterday was last day of prev month).
            // Just make sure we also stamp current month as zero-state.
            $months[] = Carbon::today()->startOfMonth();
        }

        $persisted = [];
        foreach ($months as $month) {
            $count = self::persistMonth($month, $yesterday);
            $persisted[$month->format('Y-m')] = $count;
        }
        return $persisted;
    }
}
