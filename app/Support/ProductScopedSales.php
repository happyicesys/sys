<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sales(qty) for a product-restricted viewer — THEIR SKUs only, per machine.
 *
 * Why this exists
 * ---------------
 * customers.totals_json / vends.vend_transaction_totals_json are WHOLE-MACHINE
 * rollups. Handing one to a product-restricted viewer would leak every other
 * supplier's sales at that machine, so ProductAccess::maskWholeMachineJson()
 * strips every money key — which is why the Sales(qty) column renders blank for
 * those users. The fix is not to unmask it; it is to compute a different number
 * from a product-grained source.
 *
 * Shape — deliberately identical to SyncVendTransactionTotalsJson
 * --------------------------------------------------------------
 * That job builds the whole-machine figures as
 *
 *     vend_records (completed days)  +  a LIVE today delta
 *
 * (see 'seven_days_amount' => $records7->sum('total_amount') + $todayAmount, and
 * its comment "sourced from vend_records since vend_transactions is too slow at
 * this scale"). We mirror it one grain down: vend_product_records is the
 * product-level twin of vend_records, same columns, so the only changes are the
 * table and a product_id filter. Same definition as the unrestricted column,
 * narrowed to the viewer's SKUs.
 *
 * MEASURED on live 2026-08-06, 50 busiest machines:
 *   vend_product_records, 30 days ....... instant (12,793,100c / 35,675 units)
 *   pure live vend_transaction_items 30d  TIMED OUT (max statement exec time)
 *   live, TODAY only .................... instant (99 item rows)
 * So the hybrid is not a preference, it is the only shape that completes.
 * Do NOT "simplify" this into one live query.
 *
 * vend_product_records holds NO row for today (it is a previous-day nightly
 * rollup; latest date was yesterday when this was written), which is precisely
 * why today has to come from vend_transaction_items.
 */
class ProductScopedSales
{
    /**
     * The keys this class owns. Everything else in the blob — notably
     * seven_days_all_count and the *_error_rate family — is WHOLE-MACHINE
     * health that a restricted viewer is deliberately allowed to see
     * (ProductAccess::maskWholeMachineJson keeps it, being non-money).
     * Overwriting those with a product-filtered figure would silently break the
     * Error % column, so this list stays sales-only.
     */
    private const KEYS = [
        'today_amount', 'today_count',
        'yesterday_amount', 'yesterday_count',
        'seven_days_amount', 'seven_days_count',
        'thirty_days_amount', 'thirty_days_count',
    ];

    /**
     * Per-request cache of computed rows, keyed by vend id.
     *
     * @var array<int, array<string, int>>
     */
    private static array $memo = [];

    /**
     * The vend id for a row, WITHOUT the unsafe `?? $row->id` fallback.
     *
     * The three render paths disagree about what `id` means:
     *   /vends            rows are Vend models      -> id IS the vend id, no vend_id attr
     *   /vends/customers  rows are Customer models  -> `vends.id AS vend_id` is selected,
     *                                                  and id is the CUSTOMER id
     *   customerIndexAggregates  stdClass pseudo-rows -> sets id = vend_id explicitly
     *
     * So on the customers path an UNBOUND site has vend_id = null, and blindly
     * falling back to `id` would hand us a customer id — which collides with the
     * vend id space and would attribute another machine's sales to that row.
     * When vend_id is present-but-null the answer is "no machine", i.e. null.
     */
    public static function vendIdOf($row): ?int
    {
        $raw = null;

        if ($row instanceof Model) {
            $attrs = $row->getAttributes();
            $raw = array_key_exists('vend_id', $attrs) ? $attrs['vend_id'] : ($attrs['id'] ?? null);
        } elseif (is_object($row)) {
            $raw = property_exists($row, 'vend_id') ? $row->vend_id : ($row->id ?? null);
        } elseif (is_array($row)) {
            $raw = array_key_exists('vend_id', $row) ? $row['vend_id'] : ($row['id'] ?? null);
        }

        return ($raw === null || $raw === '') ? null : ((int) $raw ?: null);
    }

    /**
     * Warm the cache for a whole page in ONE pair of queries.
     *
     * Pass the row collection itself, not ids — vendIdOf() has to see the row to
     * tell a customer id from a vend id. Calling this per row would reintroduce
     * the N+1 this class exists to avoid.
     *
     * @param  iterable<mixed>  $rows
     */
    public static function warmFor($rows): void
    {
        $products = ProductAccess::current();

        // null = unrestricted viewer: they read totals_json as before, so there
        // is nothing to compute. [] = restricted to nothing: every figure is 0,
        // and no query can tell us otherwise. Both short-circuit.
        if ($products === null) {
            return;
        }

        $vendIds = [];
        foreach ($rows as $row) {
            $id = self::vendIdOf($row);
            if ($id !== null && ! array_key_exists($id, self::$memo)) {
                $vendIds[$id] = true;
            }
        }
        $vendIds = array_keys($vendIds);

        if (empty($vendIds)) {
            return;
        }

        if ($products === []) {
            foreach ($vendIds as $id) {
                self::$memo[$id] = self::zeroRow();
            }

            return;
        }

        // Dates computed in PHP, in the APP timezone, rather than leaning on
        // MySQL's CURDATE(). They happen to agree on this deployment (both
        // UTC+8), but mark1 ships per-country with APP_TIMEZONE set per operator
        // while the DB server's tz is whatever the host says — and a one-day
        // skew here would silently move sales between Today and Y'day.
        $today = Carbon::today();
        $todayStart = $today->toDateTimeString();
        $todayDate = $today->toDateString();
        $yesterday = $today->copy()->subDay()->toDateString();
        // subDays(7): the job's window is daysVendRecords(7, 0) = SEVEN completed
        // days (D-7 .. D-1) + live today. subDays(6) would be one day short and
        // could never reconcile with the unrestricted column.
        $sevenStart = $today->copy()->subDays(7)->toDateString();
        $thirtyStart = $today->copy()->subDays(29)->toDateString();

        try {
            $rolled = self::rolledUp($vendIds, $products, $todayDate, $yesterday, $sevenStart, $thirtyStart);
            $todayRows = self::today($vendIds, $products, $todayStart);
            $todaySingleRows = self::todaySingles($vendIds, $products, $todayStart);
        } catch (\Throwable $e) {
            // Fail quiet: this runs on a normal page render, and a broken query
            // must not take the page down. The column stays blank, which is
            // exactly what it does today.
            Log::warning('ProductScopedSales failed; Sales(qty) stays blank', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($vendIds as $id) {
            $r = $rolled[$id] ?? null;
            $t = $todayRows[$id] ?? null;
            $ts = $todaySingleRows[$id] ?? null;

            // Items (multi baskets) + single transactions. Item rows only exist
            // for is_multiple baskets (verified live: 6,436 singles vs 205 multis
            // today, ZERO item rows for singles), so without the singles leg the
            // Today column missed ~97% of sales.
            $todayAmount = (int) ($t->amount ?? 0) + (int) ($ts->amount ?? 0);
            $todayCount = (int) ($t->qty ?? 0) + (int) ($ts->qty ?? 0);

            self::$memo[$id] = [
                'today_amount' => $todayAmount,
                'today_count' => $todayCount,
                'yesterday_amount' => (int) ($r->yday_amount ?? 0),
                'yesterday_count' => (int) ($r->yday_count ?? 0),
                // 7d and 30d are "completed days + today", exactly as
                // SyncVendTransactionTotalsJson composes them.
                'seven_days_amount' => (int) ($r->d7_amount ?? 0) + $todayAmount,
                'seven_days_count' => (int) ($r->d7_count ?? 0) + $todayCount,
                'thirty_days_amount' => (int) ($r->d30_amount ?? 0) + $todayAmount,
                'thirty_days_count' => (int) ($r->d30_count ?? 0) + $todayCount,
            ];
        }
    }

    /**
     * The scoped figures for one row, or null when the viewer is unrestricted
     * (or warmFor() never saw it).
     *
     * @return array<string, int>|null
     */
    public static function forRow($row): ?array
    {
        if (ProductAccess::current() === null) {
            return null;
        }

        $id = self::vendIdOf($row);

        return $id === null ? null : (self::$memo[$id] ?? null);
    }

    /**
     * Merge the scoped figures over a masked totals blob, so the Vue cells —
     * which gate on `'today_amount' in vendTransactionTotalsJson` — light up
     * again with the viewer's own numbers instead of nothing.
     *
     * Only self::KEYS are written. Machine-health keys already surviving the
     * mask are left exactly as they are.
     */
    public static function mergeInto($masked, $row)
    {
        $scoped = self::forRow($row);

        if ($scoped === null) {
            return $masked;
        }

        if (is_object($masked)) {
            foreach ($scoped as $k => $v) {
                $masked->{$k} = $v;
            }

            return $masked;
        }

        if (is_array($masked)) {
            return array_merge($masked, $scoped);
        }

        // totals_json was NULL for this row (192 sites on live had none — mostly
        // active sites with no machine bound). The viewer still deserves their
        // own numbers, so emit them standalone rather than staying blank.
        return $scoped;
    }

    /** Reset between requests/tests, and for long-lived workers. */
    public static function flush(): void
    {
        self::$memo = [];
    }

    /**
     * Completed days from the product-level rollup. One query, all vends.
     *
     * Windows match the job: yesterday = D-1; 7d = last 7 completed days
     * (daysVendRecords(7, 0)); 30d = last 29 completed days
     * (daysVendRecords(29, 0)). Today is added by the caller, because
     * vend_product_records has no row for it.
     */
    private static function rolledUp(array $vendIds, array $products, string $todayDate, string $yesterday, string $sevenStart, string $thirtyStart)
    {
        return DB::table('vend_product_records')
            ->selectRaw('vend_id')
            ->selectRaw('SUM(CASE WHEN `date` = ? THEN total_amount ELSE 0 END) AS yday_amount', [$yesterday])
            ->selectRaw('SUM(CASE WHEN `date` = ? THEN total_count ELSE 0 END) AS yday_count', [$yesterday])
            ->selectRaw('SUM(CASE WHEN `date` >= ? THEN total_amount ELSE 0 END) AS d7_amount', [$sevenStart])
            ->selectRaw('SUM(CASE WHEN `date` >= ? THEN total_count ELSE 0 END) AS d7_count', [$sevenStart])
            ->selectRaw('SUM(total_amount) AS d30_amount')
            ->selectRaw('SUM(total_count) AS d30_count')
            ->whereIn('vend_id', $vendIds)
            ->whereIn('product_id', $products)
            ->where('date', '>=', $thirtyStart)
            ->where('date', '<', $todayDate)
            ->groupBy('vend_id')
            ->get()
            ->keyBy('vend_id');
    }

    /**
     * Today only, live. Cheap because it is a single day: the
     * (vend_id, transaction_datetime) index makes it a narrow range scan.
     *
     * settlement_status = SETTLED is the same gate scopeIsSuccessful() applies.
     * Error'd and refunded items are excluded so the number means "sold",
     * consistent with the rollup.
     */
    private static function today(array $vendIds, array $products, string $todayStart)
    {
        return DB::table('vend_transaction_items as vti')
            ->join('vend_transactions as vt', 'vt.id', '=', 'vti.vend_transaction_id')
            ->selectRaw('vt.vend_id')
            ->selectRaw('SUM(vti.unit_price_amount) AS amount')
            ->selectRaw('COUNT(*) AS qty')
            ->whereIn('vt.vend_id', $vendIds)
            ->whereIn('vti.product_id', $products)
            // Bucket strictly by the flag, like the rollup job: a rare
            // single-flagged transaction that ALSO has item rows (seen live)
            // must not be counted here AND in todaySingles().
            ->where('vt.is_multiple', true)
            ->where('vt.transaction_datetime', '>=', $todayStart)
            ->where('vt.settlement_status', \App\Models\VendTransaction::SETTLEMENT_SETTLED)
            // Success = code 0/6 or NULL, matching StoreVendProductRecords'
            // item-level CASE exactly - `= 0` alone silently dropped code-6 and
            // NULL items that the completed-day rollup counts as sold.
            ->where(function ($q) {
                $q->whereIn('vti.vend_channel_error_code', [0, 6])
                    ->orWhereNull('vti.vend_channel_error_code');
            })
            ->where('vti.is_refunded', 0)
            ->groupBy('vt.vend_id')
            ->get()
            ->keyBy('vend_id');
    }

    /**
     * Today's SINGLE transactions, live. vend_transaction_items rows are only
     * created for is_multiple baskets (see VendTransactionService /
     * GatewayVendTransactionService), so singles - the overwhelming majority of
     * sales - must come from vend_transactions itself, exactly as
     * StoreVendProductRecords' single leg does: same product resolution
     * (COALESCE(vt.product_id, vc.product_id)), same amount > 0 gate, same
     * success rule (no error, or vend_channel_errors.code 0/6).
     */
    private static function todaySingles(array $vendIds, array $products, string $todayStart)
    {
        return DB::table('vend_transactions as vt')
            ->leftJoin('vend_channels as vc', 'vt.vend_channel_id', '=', 'vc.id')
            ->leftJoin('vend_channel_errors as vce', 'vt.vend_channel_error_id', '=', 'vce.id')
            ->selectRaw('vt.vend_id')
            ->selectRaw('SUM(vt.amount) AS amount')
            ->selectRaw('SUM(COALESCE(vt.qty, 1)) AS qty')
            ->whereIn('vt.vend_id', $vendIds)
            ->where(function ($q) {
                $q->where('vt.is_multiple', false)->orWhereNull('vt.is_multiple');
            })
            ->whereIn(DB::raw('COALESCE(vt.product_id, vc.product_id)'), $products)
            ->where('vt.amount', '>', 0)
            ->where('vt.transaction_datetime', '>=', $todayStart)
            ->where('vt.settlement_status', \App\Models\VendTransaction::SETTLEMENT_SETTLED)
            ->where(function ($q) {
                $q->whereNull('vt.vend_channel_error_id')->orWhereIn('vce.code', [0, 6]);
            })
            ->where('vt.is_refunded', 0)
            ->groupBy('vt.vend_id')
            ->get()
            ->keyBy('vend_id');
    }

    /** @return array<string, int> */
    private static function zeroRow(): array
    {
        return array_fill_keys(self::KEYS, 0);
    }
}
