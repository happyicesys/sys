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
 *   R+U    Fix Rental + Utility        +value + value2  (both flat $/period)
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
    public const CONTRACT_TYPE_RENTAL_UTILITY = 'R+U';
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
        float $gstRatePct = 0.0,
        float $flatDayRatio = 1.0
    ): int {
        if ($contractType === null) {
            return 0;
        }

        $value = $value !== null ? (float) $value : 0.0;
        $value2 = $value2 !== null ? (float) $value2 : 0.0;
        $psTerm = $psTerm !== null ? (float) $psTerm : 0.0;

        // $flatDayRatio prorates the TIME-BASED (flat $/period) fee components
        // when a site is active only part of the month — i.e. the month it
        // becomes Active or is Removed. 1.0 (default) = full month, so every
        // existing caller is unchanged. The PS (sales-based) portion is NOT
        // prorated: a removed/not-yet-active site has no transactions outside
        // its active window, so the sales basis is already naturally bounded.
        $r = $flatDayRatio;
        if ($r < 0.0) {
            $r = 0.0;
        } elseif ($r > 1.0) {
            $r = 1.0;
        }

        switch ($contractType) {
            case self::CONTRACT_TYPE_FREE:
                return 0;

            case self::CONTRACT_TYPE_SUBSIDIZED:
                // Subsidy is INCOME from the location (negative location fee).
                // value is in dollars → convert to cents.
                return -1 * (int) round($value * 100 * $r);

            case self::CONTRACT_TYPE_RENTAL:
            case self::CONTRACT_TYPE_UTILITY:
                // Flat amount in dollars per period
                return (int) round($value * 100 * $r);

            case self::CONTRACT_TYPE_RENTAL_UTILITY:
                // Fix Rental ($value) + Utility ($value2), both flat $/period.
                return (int) round($value * 100 * $r) + (int) round($value2 * 100 * $r);

            case self::CONTRACT_TYPE_PS:
                return self::psAmountCents($salesCents, $psTerm, $value, $gstRatePct);

            case self::CONTRACT_TYPE_PS_U:
                return self::psAmountCents($salesCents, $psTerm, $value, $gstRatePct)
                    + (int) round($value2 * 100 * $r);

            case self::CONTRACT_TYPE_PS_OR_U:
                return max(
                    self::psAmountCents($salesCents, $psTerm, $value, $gstRatePct),
                    (int) round($value2 * 100 * $r)
                );
        }

        return 0;
    }

    /**
     * Fraction (0.0–1.0) of the calendar month that a site was ACTIVE, given
     * its active_date (start of the current active interval; callers pass
     * active_date ?? begin_date) and removed_date (end of the interval, NULL
     * while active). Used to prorate flat fees for the activation / removal
     * month. A month fully inside the active window returns 1.0.
     *
     * The active window is HALF-OPEN: [active_date, removed_date). The
     * activation day is billable (the site goes live that day), but the
     * REMOVAL day is the first NON-active day — the site stops operating on
     * it, so it is NOT charged. A site removed on the 16th of a 30-day month
     * is active the 1st–15th → 15/30. (A removal on the 1st = 0 active days
     * that month.)
     *
     * The denominator is the full calendar-month day count (not the as-of
     * window) because flat fees are billed per calendar month, regardless of
     * how far the nightly run has progressed.
     */
    public static function computeActiveDayRatio(
        $activeDate,
        $removedDate,
        CarbonInterface $monthStart
    ): float {
        $mStart = Carbon::parse($monthStart)->startOfMonth();
        $mEnd = $mStart->copy()->endOfMonth()->startOfDay();
        $monthDays = (int) $mStart->daysInMonth;

        $winStart = $mStart->copy();
        if ($activeDate) {
            $a = Carbon::parse($activeDate)->startOfDay();
            if ($a->gt($winStart)) {
                $winStart = $a;
            }
        }
        $winEnd = $mEnd->copy();
        if ($removedDate) {
            // Exclusive removal: the site stops operating ON the removal date,
            // so the last ACTIVE (billable) day is the day before it.
            $lastActive = Carbon::parse($removedDate)->startOfDay()->subDay();
            if ($lastActive->lt($winEnd)) {
                $winEnd = $lastActive;
            }
        }

        if ($winEnd->lt($winStart) || $monthDays <= 0) {
            return 0.0;
        }

        $activeDays = $winStart->diffInDays($winEnd) + 1; // inclusive
        if ($activeDays >= $monthDays) {
            return 1.0;
        }

        return $activeDays / $monthDays;
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
     *
     * $forceSingleRow (one-off backfill mode):
     *   - Skips the mid-month contract SEGMENTATION pass entirely → every
     *     customer gets exactly ONE whole-month row computed from their CURRENT
     *     contract values (the customers.contract_* columns).
     *   - Bypasses the locked-row preservation → locked rows in the month are
     *     wiped and rebuilt too. Use ONLY when you explicitly want to treat
     *     today's setup as the new latest and overwrite history.
     *   - Stamps segmentation_overridden = true on every inserted row, so the
     *     nightly aggregator will NOT re-split those months later.
     *   Default false → preserves existing behaviour for nightly + ad-hoc runs.
     */
    public static function persistMonth(CarbonInterface $reference, ?CarbonInterface $asOf = null, bool $forceSingleRow = false, bool $respectLocked = false): int
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

        // "# of Job" — count of ops job items that serviced each customer in
        // the window. One row per (customer, item), so a job touching several
        // of the customer's vends counts each vend. "Delivered" is an
        // ITEM-level status (ops_job_items.status), mirroring how
        // OpsJobController derives its delivered count — the parent
        // ops_jobs.status does NOT reflect per-item completion. We count items
        // that reached at least Stock In (DELIVERED = 3) and aren't Cancelled
        // (99) — this matches the OpsJob page's "delivered" definition and so
        // also includes Verified (4) and Flagged (98). Each job is placed in
        // the month by ops_jobs.date.
        $jobCountByCustomer = DB::table('ops_job_items')
            ->join('ops_jobs', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
            ->whereNotNull('ops_job_items.customer_id')
            ->where('ops_job_items.status', '>=', \App\Models\OpsJob::STATUS_DELIVERED)
            ->where('ops_job_items.status', '<>', \App\Models\OpsJob::STATUS_CANCELLED)
            ->whereBetween('ops_jobs.date', [$windowStart, $windowEnd])
            ->groupBy('ops_job_items.customer_id')
            ->selectRaw('ops_job_items.customer_id AS customer_id, COUNT(*) AS job_count')
            ->pluck('job_count', 'customer_id');

        // We also need to emit "zero" rows for active customers that had no
        // transactions in the window — otherwise they'd disappear from the
        // Summary page even though their contract fees still apply (e.g. fix
        // rental we still pay).
        //
        // Inclusion is gated by the site's ACTIVE WINDOW (current-window only,
        // per the agreed design):
        //   - active start: active_date if set, else begin_date (legacy rows
        //     not yet seeded). The site must have started being active on/before
        //     period_end to qualify for this month.
        //   - removed end: removed_date. The site must NOT have been removed
        //     before this month started. NULL removed_date = still active.
        // (termination_date / Inactive is record-only and does NOT gate the
        // calc — Removed Date is the commission cutoff.)
        $endOfPeriod = $periodEnd->copy()->endOfDay();
        $customerQuery = Customer::query()
            ->withoutGlobalScopes() // aggregator runs system-wide
            ->where(function ($q) use ($endOfPeriod) {
                // active_date <= period_end, OR (no active_date AND begin_date
                // null/<= period_end) so legacy un-seeded rows still appear.
                $q->where(function ($q2) use ($endOfPeriod) {
                    $q2->whereNotNull('active_date')
                       ->where('active_date', '<=', $endOfPeriod);
                })->orWhere(function ($q2) use ($endOfPeriod) {
                    $q2->whereNull('active_date')
                       ->where(function ($q3) use ($endOfPeriod) {
                           $q3->whereNull('begin_date')->orWhere('begin_date', '<=', $endOfPeriod);
                       });
                });
            })
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('removed_date')->orWhere('removed_date', '>=', $monthStart);
            });

        // Locked rows are user-frozen snapshots — the aggregator must NOT
        // overwrite them. Collect the locked customer_ids for this month so we
        // can both skip building fresh payloads for them and exclude them from
        // the delete-and-reinsert below. (The current in-progress month is
        // never lockable, so nightly current-month runs see an empty set and
        // behave exactly as before; this only matters for explicit backfills
        // over historical months.)
        //
        // $forceSingleRow short-circuits this: a one-off backfill that wants
        // today's setup applied to every row treats no row as locked and
        // overwrites them all.
        if ($forceSingleRow && !$respectLocked) {
            $lockedCustomerIds = [];
            $lockedCustomerIdSet = [];
        } else {
            // $respectLocked keeps locked rows frozen even in force-single mode
            // (the "consolidate current month" seeder), so a Paid/Locked row is
            // never overwritten while every unlocked row collapses to one.
            $lockedCustomerIds = CustomerPeriodSummary::query()
                ->where('year_month', $monthStart->toDateString())
                ->whereNotNull('locked_at')
                ->pluck('customer_id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $lockedCustomerIdSet = array_flip($lockedCustomerIds);
        }

        // ── Mid-month contract SEGMENTATION setup ──────────────────────────
        // A month whose segments the user explicitly MERGED stays single-row
        // (recomputed under the latest contract) — the aggregator must NOT
        // re-split it. Collect those customers and preserve the flag on
        // re-insert.
        //
        // $forceSingleRow short-circuits the whole segmentation pass: every
        // customer gets a single whole-month row regardless of mid-month
        // contract changes, and every inserted row is stamped overridden=true
        // so future nightly runs leave them merged.
        if ($forceSingleRow) {
            $overriddenCustomerIds = [];
            $overriddenSet = [];
            $inMonthChangeCustomerIds = [];
            $segmentCandidateSet = [];
            $candidateVersions = [];
        } else {
            $overriddenCustomerIds = CustomerPeriodSummary::query()
                ->where('year_month', $monthStart->toDateString())
                ->where('segmentation_overridden', true)
                ->pluck('customer_id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $overriddenSet = array_flip($overriddenCustomerIds);

            // Customers whose contract changed strictly INSIDE this month's window
            // (a change effective on/before monthStart applies to the whole month →
            // no split). Only these can ever produce more than one segment, so the
            // common case stays exactly as before (one batched pass, single row).
            //
            // SPLIT ONLY ON SCHEDULED ("set future contract") CHANGES.
            // source = 'system' is stamped exclusively by ApplyScheduledContracts
            // when a future-dated contract reaches its effective date. Ad-hoc edits
            // on the Customer Edit page stamp source = 'user' (a correction to the
            // CURRENT term, not a mid-month change) and seeders stamp 'seeder';
            // neither should carve the month into segments. So a plain edit now
            // updates the whole-month row in place, while a future contract still
            // splits the month on its effective date (boundary filter mirrors this
            // in buildMonthSegments).
            $monthStartDay = $monthStart->copy()->startOfDay();
            $windowEndForChanges = $periodEnd->copy()->endOfDay();
            $inMonthChangeCustomerIds = DB::table('customer_contract_logs')
                ->whereNotNull('customer_id')
                ->where('source', 'system')
                ->where('effective_from', '>', $monthStartDay)
                ->where('effective_from', '<=', $windowEndForChanges)
                ->distinct()
                ->pluck('customer_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            $segmentCandidateSet = [];
            foreach ($inMonthChangeCustomerIds as $cid) {
                // Locked rows are frozen; overridden months stay merged.
                if (!isset($lockedCustomerIdSet[$cid]) && !isset($overriddenSet[$cid])) {
                    $segmentCandidateSet[$cid] = true;
                }
            }

            // Contract versions overlapping this month for the candidates only —
            // one batched query. Drives per-segment bounds + contract resolution.
            $candidateVersions = [];
            if (!empty($segmentCandidateSet)) {
                $versionRows = DB::table('customer_contract_logs')
                    ->whereIn('customer_id', array_keys($segmentCandidateSet))
                    ->where('effective_from', '<=', $windowEndForChanges)
                    ->where(function ($q) use ($monthStartDay) {
                        $q->whereNull('effective_to')->orWhere('effective_to', '>', $monthStartDay);
                    })
                    ->orderBy('customer_id')
                    ->orderBy('effective_from')
                    ->get();
                foreach ($versionRows as $vrow) {
                    $candidateVersions[(int) $vrow->customer_id][] = $vrow;
                }
            }
        }

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
            // status_id drives the "keep active sites visible in the current
            // month even before they've traded/been serviced" exception in the
            // skip-empty-row guard below (is_active is its mirror).
            'status_id',
            'operator_id',
            // Active window — drives flat-fee proration for the activation /
            // removal month (active_date falls back to begin_date).
            'begin_date',
            'active_date',
            'removed_date',
            'contract_commission_type',
            'contract_commission_value',
            'contract_commission_value2',
            'contract_ps_term',
            // External Subsidize — snapshotted per period; folds into the NET
            // location_earning_cents below.
            'is_external_subsidize',
            'external_subsidize_amount',
        ])->chunk(500, function ($customers) use ($rows, $salesByCustomer, $jobCountByCustomer, $monthStart, $periodEnd, $isCurrentMonth, $asOf, $monthEndCalendar, $now, $operatorGstRates, $lockedCustomerIdSet, $overriddenSet, $forceSingleRow, &$payloads) {
            foreach ($customers as $customer) {
                // Never regenerate a locked row — it stays as the user froze it.
                if (isset($lockedCustomerIdSet[$customer->id])) {
                    continue;
                }
                $row = $rows->get($customer->id);
                // sales_cents: from vend_transactions (cent-exact, matches
                // Transactions page). Other metrics from gp_metrics rollup.
                $salesCents = (int) round((float) ($salesByCustomer[$customer->id] ?? 0));
                $grossEarningCents = (int) ($row->gross_earning_cents ?? 0);
                $transactionCount = (int) ($row->transaction_count ?? 0);
                $vendCount = (int) ($row->vend_count ?? 0);
                // "# of Job" — pre-counted above from ops_job_items.
                $jobCount = (int) ($jobCountByCustomer[$customer->id] ?? 0);

                // Operator GST rate (e.g. 9.00 for 9%). Used by PS-family
                // formulas to de-gross the INCL-GST sales basis before
                // applying PS Term — matches the popup's math.
                $gstRatePct = (float) ($operatorGstRates[$customer->operator_id] ?? 0);

                // Flat-fee proration for the activation / removal month. 1.0 for
                // a month the site was active throughout (the common case → no
                // change). < 1.0 only when active_date starts after the 1st or
                // removed_date lands before month end. PS (sales-based) fees are
                // unaffected — sales are naturally bounded to the active days.
                $flatDayRatio = self::computeActiveDayRatio(
                    $customer->active_date ?? $customer->begin_date,
                    $customer->removed_date,
                    $monthStart
                );

                $locationFeeCents = self::computeLocationFeeCents(
                    $customer->contract_commission_type,
                    $customer->contract_commission_value !== null ? (float) $customer->contract_commission_value : null,
                    $customer->contract_commission_value2 !== null ? (float) $customer->contract_commission_value2 : null,
                    $customer->contract_ps_term !== null ? (float) $customer->contract_ps_term : null,
                    $salesCents,
                    $grossEarningCents,
                    $gstRatePct,
                    $flatDayRatio
                );

                // External Subsidize (cents) — a third party (e.g. a brand)
                // covering part of the location rental. Gated by the toggle;
                // stored in dollars on the customer. It REDUCES the operator's
                // effective location cost, so Vend Earning is computed against
                // the NET location fee: Vend Earning = Gross − (LocFee − ExtSub).
                $externalSubsidizeCents = ($customer->is_external_subsidize && $customer->external_subsidize_amount !== null)
                    ? (int) round(((float) $customer->external_subsidize_amount) * 100)
                    : 0;

                $netLocationFeeCents = $locationFeeCents - $externalSubsidizeCents;
                $locationEarningCents = $grossEarningCents - $netLocationFeeCents;
                $locationEarningRate = $salesCents > 0
                    ? round($locationEarningCents / $salesCents, 4)
                    : 0;

                // Skip rows that have nothing useful to show: no sales AND no
                // contract fee AND no ops jobs. Keeps the table compact for
                // very long-tail customers that didn't trade in the window.
                // (A standalone subsidy with zero rental/sales isn't meaningful
                // to surface.) job_count is included so a customer that only
                // had a service visit (no sales yet) still surfaces with its
                // "# of Job" count.
                //
                // EXCEPTION — current in-progress month + ACTIVE or REMOVED site:
                // always emit the row even when all four metrics are still zero,
                // so a site that is in its commission window THIS month never
                // vanishes from the "Current" view in the gap between its last
                // sale/job and its next one (e.g. a machine serviced early-month
                // with its next job not due until later, a PS contract whose fee
                // is 0 until it trades, or a site being REMOVED this month whose
                // prorated fee/sales happen to net to 0).
                //
                // Removed is included because the eligibility query above already
                // restricts to removed_date >= monthStart — i.e. the site was
                // still live for part of THIS month (removed-this-month). A site
                // removed in an EARLIER month is excluded upstream, so its last
                // row correctly stays in its removal month and the current month
                // shows nothing for it.
                //
                // Scoped to is_current_month so SETTLED historical months stay
                // compact (only months with real activity are stored). New /
                // Potential / Inactive sites are still NOT surfaced as empty
                // noise. This only ADDS current-month rows — it never removes any
                // row that was being emitted before.
                $isEmptyRow = $salesCents === 0 && $locationFeeCents === 0 && $transactionCount === 0 && $jobCount === 0;
                $keepInWindowCurrentMonth = $isCurrentMonth
                    && in_array((int) $customer->status_id, [
                        \App\Models\Customer::STATUS_ACTIVE,
                        \App\Models\Customer::STATUS_REMOVED,
                    ], true);
                if ($isEmptyRow && !$keepInWindowCurrentMonth) {
                    continue;
                }

                // Keyed by customer_id (one whole-month payload per customer).
                // Segment-candidate customers get expanded into per-segment
                // payloads after the loop; everyone else stays this single row.
                $payloads[$customer->id] = [
                    'customer_id' => $customer->id,
                    'operator_id' => $customer->operator_id,
                    'year_month' => $monthStart->toDateString(),
                    'period_start' => $monthStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'is_current_month' => $isCurrentMonth,
                    'segment_index' => 0,
                    // force-single-row mode marks every row as overridden so the
                    // nightly aggregator won't try to re-split them later.
                    // Force-single backfills stamp overridden=true so nightly
                    // won't re-split. But the "consolidate current month" seeder
                    // ($respectLocked) must NOT lock the month down — a later
                    // "Set Upcoming Term" applied mid-month still needs to split
                    // it — so it leaves overridden as the existing value.
                    'segmentation_overridden' => ($forceSingleRow && !$respectLocked)
                        ? true
                        : isset($overriddenSet[$customer->id]),
                    'as_of_date' => ($isCurrentMonth ? $asOf : $monthEndCalendar)->toDateString(),
                    'sales_cents' => $salesCents,
                    'gross_earning_cents' => $grossEarningCents,
                    'location_fees_cents' => $locationFeeCents,
                    'location_earning_cents' => $locationEarningCents,
                    'location_earning_rate' => $locationEarningRate,
                    'external_subsidize_cents' => $externalSubsidizeCents,
                    'transaction_count' => $transactionCount,
                    'vend_count' => $vendCount,
                    'job_count' => $jobCount,
                    'contract_commission_type' => $customer->contract_commission_type,
                    'contract_commission_value' => $customer->contract_commission_value,
                    'contract_commission_value2' => $customer->contract_commission_value2,
                    'contract_ps_term' => $customer->contract_ps_term,
                    'contract_log_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        });

        // Expand segment-candidate customers (a contract change happened inside
        // this month) into per-segment payloads; every other customer keeps its
        // single whole-month row. Keyed → flat list for the insert below.
        $finalPayloads = [];
        foreach ($payloads as $cid => $payload) {
            if (isset($segmentCandidateSet[$cid]) && !empty($candidateVersions[$cid])) {
                $segments = self::buildMonthSegments($candidateVersions[$cid], $monthStart, $periodEnd);
                if (count($segments) > 1) {
                    foreach (self::buildSegmentPayloads(
                        $payload, $segments, $monthStart, $isCurrentMonth, $asOf, $now, $operatorGstRates, $testingVendIds
                    ) as $segPayload) {
                        $finalPayloads[] = $segPayload;
                    }
                    continue;
                }
            }
            $finalPayloads[] = $payload;
        }
        $payloads = array_values($finalPayloads);

        if (empty($payloads)) {
            // Still wipe any stale rows for this month that no longer qualify —
            // but leave locked rows intact.
            CustomerPeriodSummary::query()
                ->where('year_month', $monthStart->toDateString())
                ->when(!empty($lockedCustomerIds), fn ($q) => $q->whereNotIn('customer_id', $lockedCustomerIds))
                ->delete();
            return 0;
        }

        // Idempotent overwrite for this month — excluding locked rows, which
        // are preserved as the user's frozen snapshot.
        DB::transaction(function () use ($payloads, $monthStart, $lockedCustomerIds) {
            CustomerPeriodSummary::query()
                ->where('year_month', $monthStart->toDateString())
                ->when(!empty($lockedCustomerIds), fn ($q) => $q->whereNotIn('customer_id', $lockedCustomerIds))
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
     * Build the segment date-ranges for a customer whose contract changed
     * inside a month. Returns an ordered list of
     *   ['start' => Carbon, 'end' => Carbon, 'version' => stdClass|null]
     * where each segment is bounded by [start, end] (inclusive days) and
     * `version` is the contract_contract_logs row in effect during it.
     *
     * Segment starts = month start + each in-month change date; a segment runs
     * until the day before the next change (or the month's period_end for the
     * last segment). The day a new contract version becomes effective belongs
     * to that new version (standard effective-dating).
     */
    protected static function buildMonthSegments($versions, CarbonInterface $monthStart, CarbonInterface $periodEnd): array
    {
        $monthStartStr = $monthStart->toDateString();
        $periodEndStr = $periodEnd->toDateString();

        // A new segment boundary is created ONLY by a scheduled ("set future
        // contract") change — source = 'system'. Ad-hoc edits (source = 'user')
        // and seeders (source = 'seeder') write log rows too, but they are
        // corrections to the current term, not mid-month changes, so they must
        // NOT cut the month (mirrors the candidacy filter in persistMonth). All
        // versions are still used below for VALUE resolution; only the boundary
        // set is restricted here.
        $starts = [$monthStartStr => true];
        foreach ($versions as $v) {
            if (($v->source ?? null) !== 'system') {
                continue;
            }
            $effDate = Carbon::parse($v->effective_from)->toDateString();
            if ($effDate > $monthStartStr && $effDate <= $periodEndStr) {
                $starts[$effDate] = true;
            }
        }
        $startList = array_keys($starts);
        sort($startList);

        // Earliest known version for this customer (the candidate set is small).
        // Used to BACKFILL the leading part of the month when the contract-log
        // history starts mid-month. The customer's CONTRACT is the system of
        // record for the fee — the audit log merely captures it from whenever it
        // was first saved in mark1 — so a month whose first log lands mid-month
        // must NOT be read as "no contract before that date". We assume the
        // earliest logged version applied from the start (its proration is still
        // bounded to the site's active_date downstream). Without this, a
        // first-ever save or a re-save dated mid-month manufactures a phantom
        // "no-contract → contract" split (e.g. site 16866: two identical R$60
        // re-saves on 06-16 were splitting June into a bogus 1st–15th row). A
        // GENUINE mid-month change still splits, because the later version's
        // money signature differs (contractVersionsEquivalent).
        $earliest = null;
        foreach ($versions as $v) {
            if ($earliest === null
                || Carbon::parse($v->effective_from)->lt(Carbon::parse($earliest->effective_from))) {
                $earliest = $v;
            }
        }

        $segments = [];
        $n = count($startList);
        for ($i = 0; $i < $n; $i++) {
            $segStart = Carbon::parse($startList[$i])->startOfDay();
            $segEnd = ($i + 1 < $n)
                ? Carbon::parse($startList[$i + 1])->subDay()->startOfDay()
                : Carbon::parse($periodEndStr)->startOfDay();
            if ($segEnd->lt($segStart)) {
                continue;
            }
            // versionActiveAt returns null only for the leading gap before the
            // earliest log; backfill it with the earliest version (see above).
            $ver = self::versionActiveAt($versions, $segStart->copy()->endOfDay())
                ?? $earliest;
            $segments[] = [
                'start' => $segStart,
                'end' => $segEnd,
                'version' => $ver,
            ];
        }

        // Collapse adjacent segments whose contract values are IDENTICAL. A
        // customer_contract_logs row only marks a real split when the contract
        // it points to actually differs from the one before it — a re-save, a
        // seeded duplicate, or a metadata-only edit (remarks / auto-renewal /
        // notice period) must NOT carve the month into two financially
        // identical rows. By merging here, splitting depends on a genuine
        // change in plan / fees / contract dates, regardless of how the log
        // rows were written. If everything collapses to one segment the caller
        // keeps the single whole-month row.
        $merged = [];
        foreach ($segments as $seg) {
            if (!empty($merged)) {
                $lastIdx = count($merged) - 1;
                if (self::contractVersionsEquivalent($merged[$lastIdx]['version'], $seg['version'])) {
                    // Extend the previous segment over this one (same contract).
                    $merged[$lastIdx]['end'] = $seg['end'];
                    continue;
                }
            }
            $merged[] = $seg;
        }

        return $merged;
    }

    /**
     * Whether two contract-log versions represent the SAME deal for
     * segmentation purposes. Compares ONLY the fields that actually change the
     * money in a summary row — commission plan / value(s), PS term, and
     * effective external subsidy — with type-aware normalisation (so "9" ==
     * "9.00", etc.).
     *
     * Deliberately ignores contract_until / contract_from, auto-renewal,
     * notice period and remarks. None of those change any figure in the row,
     * and the page shows the customer's LIVE value for them on every row, so
     * splitting a month on one of them just produces two financially identical
     * rows (the exact "consistent data splitting" we want to avoid — e.g. a
     * seeded end-date being corrected to the real value). A split only survives
     * when the fees genuinely differ.
     *
     * null == null (no contract on either side). null vs a version = different.
     */
    protected static function contractVersionsEquivalent($a, $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }

        // null / '' / 0 all mean "no value" for these commission fields, so
        // they must compare EQUAL — otherwise a version with value2 = null and
        // another with value2 = 0 reads as a phantom change and splits the
        // month into two identical rows. A genuine change (e.g. 0 → 80) is
        // still caught.
        $num = fn ($v) => ($v === null || $v === '') ? '0.0000' : number_format((float) $v, 4, '.', '');
        $str = fn ($v) => $v === null ? '' : trim((string) $v);
        // Effective subsidy: amount only counts when the toggle is on, so a
        // stale amount behind an off toggle doesn't read as a difference.
        $effSub = fn ($v) => ((bool) $v->is_external_subsidize && $v->external_subsidize_amount !== null)
            ? number_format((float) $v->external_subsidize_amount, 4, '.', '')
            : '0';

        return $str($a->contract_commission_type) === $str($b->contract_commission_type)
            && $num($a->contract_commission_value) === $num($b->contract_commission_value)
            && $num($a->contract_commission_value2) === $num($b->contract_commission_value2)
            && $num($a->contract_ps_term) === $num($b->contract_ps_term)
            && $effSub($a) === $effSub($b);
    }

    /**
     * The contract version in effect at $moment, or null. A version covers
     * [effective_from, effective_to); effective_to NULL = currently active.
     */
    protected static function versionActiveAt($versions, CarbonInterface $moment)
    {
        $found = null;
        foreach ($versions as $v) {
            $from = Carbon::parse($v->effective_from);
            $to = $v->effective_to !== null ? Carbon::parse($v->effective_to) : null;
            if ($from->lte($moment) && ($to === null || $to->gt($moment))) {
                $found = $v;
            }
        }
        return $found;
    }

    /**
     * Build per-segment payloads for one segmented customer. Each segment runs
     * date-bounded aggregation (sales / gross / counts / jobs) over its own
     * range and computes Location Fees from its own contract version. Mirrors
     * the whole-month query filters exactly so a merged month reconciles to the
     * sum of its segments.
     *
     * Only the segment-candidate customers (rare — those with a mid-month
     * contract change) hit these per-segment queries, so page-wide cost is
     * bounded.
     */
    protected static function buildSegmentPayloads(
        array $base,
        array $segments,
        CarbonInterface $monthStart,
        bool $isCurrentMonth,
        CarbonInterface $asOf,
        $now,
        array $operatorGstRates,
        array $testingVendIds
    ): array {
        $customerId = $base['customer_id'];
        $operatorId = $base['operator_id'];
        $gstRatePct = (float) ($operatorGstRates[$operatorId] ?? 0);

        $out = [];
        $lastIndex = count($segments) - 1;

        foreach ($segments as $idx => $seg) {
            /** @var \Carbon\Carbon $segStart */
            $segStart = $seg['start'];
            /** @var \Carbon\Carbon $segEnd */
            $segEnd = $seg['end'];
            $v = $seg['version'];

            $wStart = $segStart->copy()->startOfDay();
            $wEnd = $segEnd->copy()->endOfDay();

            // Gross / counts ← gp_metrics (txn_date DATE range).
            $gp = DB::table('gp_metrics')
                ->where('customer_id', $customerId)
                ->whereBetween('txn_date', [$segStart->toDateString(), $segEnd->toDateString()])
                ->selectRaw('COALESCE(SUM(gross_profit_cents), 0) AS gross_earning_cents')
                ->selectRaw('COALESCE(SUM(transaction_count), 0) AS transaction_count')
                ->selectRaw('COUNT(DISTINCT vend_id) AS vend_count')
                ->first();
            $grossEarningCents = (int) ($gp->gross_earning_cents ?? 0);
            $transactionCount = (int) ($gp->transaction_count ?? 0);
            $vendCount = (int) ($gp->vend_count ?? 0);

            // Sales ← vend_transactions, same filter as the whole-month query.
            $salesCents = (int) round((float) DB::table('vend_transactions')
                ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
                ->where('vend_transactions.customer_id', $customerId)
                ->where(function ($q) use ($wStart, $wEnd) {
                    $q->whereBetween('vend_transactions.transaction_datetime', [$wStart, $wEnd])
                      ->orWhere(function ($or) use ($wStart, $wEnd) {
                          $or->whereNull('vend_transactions.transaction_datetime')
                             ->whereBetween('vend_transactions.created_at', [$wStart, $wEnd]);
                      });
                })
                ->where(function ($q) {
                    $q->whereIn('vend_channel_errors.code', [0, 6])
                      ->orWhereNull('vend_channel_errors.code')
                      ->orWhere('vend_transactions.is_multiple', true);
                })
                ->when(!empty($testingVendIds), fn ($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
                ->where('vend_transactions.settlement_status', \App\Models\VendTransaction::SETTLEMENT_SETTLED)
                ->sum('vend_transactions.amount'));

            // Jobs ← ops_job_items (ops_jobs.date range).
            $jobCount = (int) DB::table('ops_job_items')
                ->join('ops_jobs', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
                ->where('ops_job_items.customer_id', $customerId)
                ->where('ops_job_items.status', '>=', \App\Models\OpsJob::STATUS_DELIVERED)
                ->where('ops_job_items.status', '<>', \App\Models\OpsJob::STATUS_CANCELLED)
                ->whereBetween('ops_jobs.date', [$wStart, $wEnd])
                ->count();

            // Contract terms from this segment's version (null = no contract).
            $cType = $v ? $v->contract_commission_type : null;
            $cVal = ($v && $v->contract_commission_value !== null) ? (float) $v->contract_commission_value : null;
            $cVal2 = ($v && $v->contract_commission_value2 !== null) ? (float) $v->contract_commission_value2 : null;
            $cPs = ($v && $v->contract_ps_term !== null) ? (float) $v->contract_ps_term : null;

            $locationFeeCents = self::computeLocationFeeCents($cType, $cVal, $cVal2, $cPs, $salesCents, $grossEarningCents, $gstRatePct);
            $externalSubsidizeCents = ($v && $v->is_external_subsidize && $v->external_subsidize_amount !== null)
                ? (int) round(((float) $v->external_subsidize_amount) * 100)
                : 0;
            $netLocationFeeCents = $locationFeeCents - $externalSubsidizeCents;
            $locationEarningCents = $grossEarningCents - $netLocationFeeCents;
            $locationEarningRate = $salesCents > 0 ? round($locationEarningCents / $salesCents, 4) : 0;

            // Drop wholly-empty segments (no sales / fee / txns / jobs).
            if ($salesCents === 0 && $locationFeeCents === 0 && $transactionCount === 0 && $jobCount === 0) {
                continue;
            }

            $segIsCurrent = $isCurrentMonth && ($idx === $lastIndex);

            $out[] = [
                'customer_id' => $customerId,
                'operator_id' => $operatorId,
                'year_month' => $monthStart->toDateString(),
                'period_start' => $segStart->toDateString(),
                'period_end' => $segEnd->toDateString(),
                'is_current_month' => $segIsCurrent,
                'segment_index' => $idx,
                'segmentation_overridden' => false,
                'as_of_date' => ($segIsCurrent ? $asOf->toDateString() : $segEnd->toDateString()),
                'sales_cents' => $salesCents,
                'gross_earning_cents' => $grossEarningCents,
                'location_fees_cents' => $locationFeeCents,
                'location_earning_cents' => $locationEarningCents,
                'location_earning_rate' => $locationEarningRate,
                'external_subsidize_cents' => $externalSubsidizeCents,
                'transaction_count' => $transactionCount,
                'vend_count' => $vendCount,
                'job_count' => $jobCount,
                'contract_commission_type' => $cType,
                'contract_commission_value' => $cVal,
                'contract_commission_value2' => $cVal2,
                'contract_ps_term' => $cPs,
                'contract_log_id' => $v ? $v->id : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // If every segment was empty, fall back to the single whole-month row
        // so the customer doesn't vanish from the page.
        return empty($out) ? [$base] : $out;
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
