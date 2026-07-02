<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Operator;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Run each CustomerIndex sub-query in isolation with timing.
 * Safe to run in production — read-only, no side effects.
 *
 * Usage:
 *   php artisan customer-index:debug
 *   php artisan customer-index:debug --operator=1      # filter by operator ID
 *   php artisan customer-index:debug --no-cache        # bypass cache, force DB hits
 *   php artisan customer-index:debug --per-page=100    # how many rows the main query fetches
 */
class DebugCustomerIndexPerformance extends Command
{
    protected $signature = 'customer-index:debug
                            {--operator= : Operator ID to filter by (leave blank = use first operator)}
                            {--no-cache  : Bypass cache and hit DB directly}
                            {--per-page=50 : Rows per page for the main paginated query}';

    protected $description = 'Time each CustomerIndex sub-query individually to find the production bottleneck';

    public function handle(): void
    {
        $operatorId = $this->option('operator');
        $noCache    = $this->option('no-cache');
        $perPage    = (int) $this->option('per-page') ?: 50;

        // Resolve operator
        $operator = $operatorId
            ? Operator::find($operatorId)
            : Operator::first();

        if (! $operator) {
            $this->error('No operator found. Pass --operator=<id>');
            return;
        }

        // HIPL multi-operator expansion (mirrors indexCustomer logic)
        if ($operator->code === 'HIPL') {
            $relatedCodes = ['HIPL', 'HIMD', 'LEA', 'HIESG', 'UL-ST'];
            $operatorIds  = Operator::whereIn('code', $relatedCodes)->pluck('id')->filter()->values()->toArray();
        } else {
            $operatorIds = [$operator->id];
        }

        $productAvailableDate = Carbon::today()->addDay()->toDateString();

        $this->info("Operator : {$operator->name} (#{$operator->id})" . ($operator->code === 'HIPL' ? ' [HIPL group: ' . implode(', ', $operatorIds) . ']' : ''));
        $this->info("Cache    : " . ($noCache ? 'BYPASSED' : 'enabled'));
        $this->info("PerPage  : {$perPage}");
        $this->newLine();

        // ── 1. Base count query ───────────────────────────────────────────────
        // Mirrors the $countQuery = clone $vends; $total = $countQuery->count(); path.
        $this->bench('Base COUNT (customers + standard joins, operator filter)', function () use ($operatorIds) {
            return Customer::query()
                ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
                ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
                ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
                ->leftJoin('zones', 'zones.id', '=', 'customers.zone_id')
                ->leftJoin('addresses', function ($q) {
                    $q->on('addresses.modelable_id', '=', 'customers.id')
                        ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                        ->where('addresses.type', '=', 2);
                })
                ->whereIn('customers.operator_id', $operatorIds)
                ->count();
        });

        // ── 2. Main paginated query (default sort: balance_percent) ───────────
        // Mirrors $vends->select($selectColumns)->forPage($page, $perPage)->get()
        // with no conditional aggregate joins (those come via loadAggregates).
        $this->bench("Main paginated GET (page 1, {$perPage} rows, sort: balance_percent)", function () use ($operatorIds, $perPage) {
            return Customer::query()
                ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
                ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
                ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
                ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
                ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
                ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
                ->leftJoin('zones', 'zones.id', '=', 'customers.zone_id')
                ->leftJoin('addresses', function ($q) {
                    $q->on('addresses.modelable_id', '=', 'customers.id')
                        ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                        ->where('addresses.type', '=', 2);
                })
                ->leftJoin('vend_configs', 'vend_configs.id', '=', 'vends.vend_config_id')
                ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
                ->whereIn('customers.operator_id', $operatorIds)
                ->select([
                    'customers.id AS id',
                    'vends.id AS vend_id',
                    'customers.id AS customer_id',
                    'vends.balance_percent',
                    'vends.code',
                    'customers.name',
                    'customers.is_active',
                    'customers.operator_id',
                    'operators.code AS operator_code',
                    'operators.name AS operator_name',
                    'location_types.name AS location_type_name',
                    'product_mappings.name AS product_mapping_name',
                    'zones.name AS zone_name',
                    'vend_prefixes.name AS vend_prefix_name',
                    'vend_configs.name AS vend_config_name',
                ])
                ->orderBy('vends.balance_percent', 'desc')
                ->forPage(1, $perPage)
                ->get();
        }, $pageItems);

        // Extract vend & customer IDs from the page for loadAggregates benches
        $vendIds     = collect($pageItems ?? [])->pluck('vend_id')->filter()->unique()->values()->toArray();
        $customerIds = collect($pageItems ?? [])->pluck('customer_id')->filter()->unique()->values()->toArray();
        $rowCount    = count($vendIds);
        $this->line("    → {$rowCount} distinct vend IDs on this page (used for aggregate benches below)");
        $this->newLine();

        // ── 3. loadAggregates: vc ─────────────────────────────────────────────
        $this->bench("loadAggregates vc — SUM(amount*qty/capacity) per vend ({$rowCount} vends)", function () use ($vendIds) {
            if (empty($vendIds)) return collect();
            return DB::table('vend_channels')
                ->select('vend_id', DB::raw('SUM(amount * qty) as total_stock_amount'), DB::raw('SUM(amount * capacity) as total_full_load_amount'))
                ->whereIn('vend_id', $vendIds)
                ->where('is_active', true)
                ->where('capacity', '>', 0)
                ->groupBy('vend_id')
                ->get();
        });

        // ── 4. loadAggregates: vc_cost ────────────────────────────────────────
        $this->bench("loadAggregates vc_cost — SUM(qty*cost) via unit_costs ({$rowCount} vends)", function () use ($vendIds) {
            if (empty($vendIds)) return collect();
            return DB::table('vend_channels')
                ->join('unit_costs', 'vend_channels.product_id', '=', 'unit_costs.product_id')
                ->select('vend_channels.vend_id', DB::raw('SUM(vend_channels.qty * unit_costs.cost) as total_stock_cost'))
                ->where('unit_costs.is_current', true)
                ->where('vend_channels.is_active', true)
                ->where('vend_channels.capacity', '>', 0)
                ->whereIn('vend_channels.vend_id', $vendIds)
                ->groupBy('vend_channels.vend_id')
                ->get();
        });

        // ── 5. loadAggregates: vc_stock ───────────────────────────────────────
        $this->bench("loadAggregates vc_stock — stock-to-fill with product_limits ({$rowCount} vends)", function () use ($vendIds, $productAvailableDate) {
            if (empty($vendIds)) return collect();
            return DB::table('vend_channels')
                ->join('products', 'vend_channels.product_id', '=', 'products.id')
                ->leftJoinSub(function ($query) use ($productAvailableDate) {
                    $query->select('pl.product_id', 'pl.qty', 'pl.id')
                        ->from('product_limits as pl')
                        ->join(
                            DB::raw("(SELECT product_id, MAX(id) AS max_id FROM product_limits WHERE `date` = '{$productAvailableDate}' GROUP BY product_id) AS latest_pl"),
                            'pl.id', '=', DB::raw('latest_pl.max_id')
                        );
                }, 'product_limits', 'products.id', '=', 'product_limits.product_id')
                ->select(
                    'vend_channels.vend_id',
                    DB::raw('SUM(vend_channels.amount * GREATEST(CASE WHEN product_limits.id AND product_limits.qty < vend_channels.capacity THEN (product_limits.qty - vend_channels.qty) ELSE (vend_channels.capacity - vend_channels.qty) END, 0)) AS actual_stock_in_value'),
                    DB::raw('SUM(GREATEST(CASE WHEN product_limits.id AND product_limits.qty < vend_channels.capacity THEN (product_limits.qty - vend_channels.qty) ELSE (vend_channels.capacity - vend_channels.qty) END, 0)) AS actual_stock_in_qty')
                )
                ->where('products.is_available', true)
                ->where('vend_channels.is_active', true)
                ->where('vend_channels.capacity', '>', 0)
                ->whereIn('vend_channels.vend_id', $vendIds)
                ->groupBy('vend_channels.vend_id')
                ->get();
        });

        // ── 6. loadAggregates: last_ops_jobs + last_second_ops_jobs ──────────
        // ROW_NUMBER on pre-filtered customer IDs — replaces the old CROSS JOIN LATERAL
        // approach which was ~180ms/customer (9220ms for 50). The inner subquery fetches
        // only the 50 known customers via idx_oji_cust_created, keeping total rows small.
        $this->bench("loadAggregates last/second ops_jobs — ROW_NUMBER on {$rowCount} customer IDs", function () use ($customerIds) {
            if (empty($customerIds)) return collect();
            $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
            return DB::select("
                SELECT
                    base.customer_id,
                    base.cash_amount,
                    base.acc_total_amount,
                    base.acc_total_count,
                    base.rn,
                    SUM(ojic.actual_qty * vc.amount) AS amount,
                    SUM(ojic.actual_qty) AS count
                FROM (
                    SELECT oji.id, oji.customer_id, oji.cash_amount, oji.acc_total_amount,
                           oji.acc_total_count, oji.ops_job_id,
                           ROW_NUMBER() OVER (PARTITION BY oji.customer_id ORDER BY oji.created_at DESC) AS rn
                    FROM ops_job_items oji
                    WHERE oji.customer_id IN ({$placeholders})
                    AND oji.status >= 3 AND oji.status <> 99
                ) AS base
                INNER JOIN ops_job_item_channels ojic ON base.id = ojic.ops_job_item_id
                INNER JOIN vend_channels vc ON ojic.vend_channel_id = vc.id
                INNER JOIN ops_jobs oj ON base.ops_job_id = oj.id
                WHERE base.rn <= 2 AND oj.date < CURDATE() + INTERVAL 1 DAY
                GROUP BY base.customer_id, base.rn
            ", $customerIds);
        });

        // ── 7. loadAggregates: next_ops_jobs ─────────────────────────────────
        $this->bench("loadAggregates next_ops_jobs — pending jobs ({$rowCount} customers)", function () use ($customerIds) {
            if (empty($customerIds)) return collect();
            $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
            return DB::select("
                SELECT oji.customer_id, oji.cash_amount,
                    SUM(ojic.picked_qty * vc.amount) AS amount,
                    SUM(ojic.picked_qty) AS count
                FROM ops_job_items oji
                INNER JOIN (
                    SELECT oji_inner.customer_id, MIN(oj_inner.date) AS min_date
                    FROM ops_job_items oji_inner
                    INNER JOIN ops_jobs oj_inner ON oji_inner.ops_job_id = oj_inner.id
                    WHERE oji_inner.status < 3
                    AND oj_inner.date >= CURDATE()
                    AND oji_inner.customer_id IN ({$placeholders})
                    GROUP BY oji_inner.customer_id
                ) next_job ON next_job.customer_id = oji.customer_id
                INNER JOIN ops_jobs oj ON oji.ops_job_id = oj.id AND oj.date = next_job.min_date
                INNER JOIN ops_job_item_channels ojic ON oji.id = ojic.ops_job_item_id
                INNER JOIN vend_channels vc ON ojic.vend_channel_id = vc.id
                WHERE oji.status < 3
                AND oji.customer_id IN ({$placeholders})
                GROUP BY oji.customer_id
            ", array_merge($customerIds, $customerIds));
        });

        // ── 8. loadAggregates: last_thirty_days_stock_in ─────────────────────
        // Drive from ops_jobs (idx_oj_date) so date filter reduces rows first.
        $this->bench("loadAggregates last_thirty_days_stock_in — drive from ops_jobs ({$rowCount} customers)", function () use ($customerIds) {
            if (empty($customerIds)) return collect();
            return DB::table('ops_jobs')
                ->join('ops_job_items', function ($join) use ($customerIds) {
                    $join->on('ops_job_items.ops_job_id', '=', 'ops_jobs.id')
                        ->where('ops_job_items.status', '>=', 3)
                        ->where('ops_job_items.status', '<>', 99)
                        ->whereIn('ops_job_items.customer_id', $customerIds);
                })
                ->join('ops_job_item_channels', 'ops_job_item_channels.ops_job_item_id', '=', 'ops_job_items.id')
                ->join('vend_channels', 'ops_job_item_channels.vend_channel_id', '=', 'vend_channels.id')
                ->select(
                    'ops_job_items.customer_id',
                    DB::raw('SUM(ops_job_item_channels.actual_qty) AS qty'),
                    DB::raw('SUM(ops_job_item_channels.actual_qty * vend_channels.amount) AS amount')
                )
                ->whereBetween('ops_jobs.date', [Carbon::today()->subDays(29)->toDateString(), Carbon::today()->toDateString()])
                ->groupBy('ops_job_items.customer_id')
                ->get();
        });

        // ── 9. Conditional sort subquery: vc (JOIN version used for sorting) ──
        // Only activated when sortKey is thirty_days_over_full_load_ratio / total_stock_amount / total_full_load_amount.
        // Run as a standalone subquery so we can see its raw cost.
        $this->bench('Sort subquery: vc — vend_channels SUM (all vends, no vend_id filter)', function () {
            return DB::table('vend_channels')
                ->select('vend_id', DB::raw('SUM(amount * qty) AS total_stock_amount'), DB::raw('SUM(amount * capacity) AS total_full_load_amount'))
                ->where('is_active', true)
                ->where('capacity', '>', 0)
                ->groupBy('vend_id')
                ->get();
        });

        // ── 10. Conditional sort subquery: vc_cost ───────────────────────────
        $this->bench('Sort subquery: vc_cost — vend_channels + unit_costs (all vends)', function () {
            return DB::table('vend_channels')
                ->join('unit_costs', 'vend_channels.product_id', '=', 'unit_costs.product_id')
                ->select('vend_channels.vend_id', DB::raw('SUM(vend_channels.qty * unit_costs.cost) as total_stock_cost'))
                ->where('unit_costs.is_current', true)
                ->where('vend_channels.is_active', true)
                ->where('vend_channels.capacity', '>', 0)
                ->groupBy('vend_channels.vend_id')
                ->get();
        });

        // ── 11. Conditional sort subquery: vc_stock ──────────────────────────
        $this->bench('Sort subquery: vc_stock — product_limits capacity-fill (all vends)', function () use ($productAvailableDate) {
            return DB::select("
                SELECT
                    vend_channels.vend_id,
                    SUM(
                        vend_channels.amount *
                        GREATEST(
                            CASE
                                WHEN product_limits.id AND product_limits.qty < vend_channels.capacity THEN
                                    (product_limits.qty - vend_channels.qty)
                                ELSE
                                    (vend_channels.capacity - vend_channels.qty)
                            END,
                            0
                        )
                    ) AS actual_stock_in_value
                FROM vend_channels
                INNER JOIN products ON vend_channels.product_id = products.id
                LEFT JOIN (
                    SELECT pl.product_id, pl.qty, pl.id
                    FROM product_limits AS pl
                    INNER JOIN (
                        SELECT product_id, MAX(id) AS max_id
                        FROM product_limits
                        WHERE `date` = ?
                        GROUP BY product_id
                    ) AS latest_pl ON pl.id = latest_pl.max_id
                ) AS product_limits ON products.id = product_limits.product_id
                WHERE products.is_available = true
                AND vend_channels.is_active = true
                AND vend_channels.capacity > 0
                GROUP BY vend_channels.vend_id
            ", [$productAvailableDate]);
        });

        // ── 12. Conditional sort subquery: last_ops_jobs (pre-fetched IN list) ─────────────
        // Pre-fetch operator customer IDs in PHP (one fast indexed query) then embed them as
        // a hard IN list — MySQL uses BKA probes via idx_oji_cust_created (customer_id,
        // created_at) rather than risking an incorrect join order with an INNER JOIN.
        $benchCustomerIds = DB::table('customers')
            ->whereIn('operator_id', $operatorIds)
            ->pluck('id')
            ->map(fn($v) => (int)$v)
            ->all();
        $benchCustomerIn = $benchCustomerIds ? implode(',', $benchCustomerIds) : '0';

        $this->bench('Sort subquery: last_ops_jobs — pre-fetched IN list ROW_NUMBER rn=1 (' . count($benchCustomerIds) . ' customers)', function () use ($benchCustomerIn) {
            return DB::select("
                SELECT oji.customer_id, oji.cash_amount, oji.acc_total_amount, oji.acc_total_count,
                    SUM(ojic.actual_qty * vc.amount) AS amount, SUM(ojic.actual_qty) AS count
                FROM (
                    SELECT oji_inner.id, oji_inner.customer_id, oji_inner.cash_amount,
                           oji_inner.acc_total_amount, oji_inner.acc_total_count, oji_inner.ops_job_id,
                           ROW_NUMBER() OVER (PARTITION BY oji_inner.customer_id ORDER BY oji_inner.created_at DESC) AS rn
                    FROM ops_job_items oji_inner
                    WHERE oji_inner.customer_id IN ({$benchCustomerIn})
                    AND oji_inner.status >= 3 AND oji_inner.status <> 99
                ) oji
                INNER JOIN ops_job_item_channels ojic ON oji.id = ojic.ops_job_item_id
                INNER JOIN vend_channels vc ON ojic.vend_channel_id = vc.id
                INNER JOIN ops_jobs oj ON oji.ops_job_id = oj.id
                WHERE oji.rn = 1 AND oj.date < CURDATE() + INTERVAL 1 DAY
                GROUP BY oji.customer_id
            ");
        });

        // ── 13. Conditional sort subquery: last_second_ops_jobs (pre-fetched IN list) ───────
        $this->bench('Sort subquery: last_second_ops_jobs — pre-fetched IN list ROW_NUMBER rn=2 (' . count($benchCustomerIds) . ' customers)', function () use ($benchCustomerIn) {
            return DB::select("
                SELECT oji.customer_id, oji.cash_amount, oji.acc_total_amount, oji.acc_total_count,
                    SUM(ojic.actual_qty * vc.amount) AS amount, SUM(ojic.actual_qty) AS count
                FROM (
                    SELECT oji_inner.id, oji_inner.customer_id, oji_inner.cash_amount,
                           oji_inner.acc_total_amount, oji_inner.acc_total_count, oji_inner.ops_job_id,
                           ROW_NUMBER() OVER (PARTITION BY oji_inner.customer_id ORDER BY oji_inner.created_at DESC) AS rn
                    FROM ops_job_items oji_inner
                    WHERE oji_inner.customer_id IN ({$benchCustomerIn})
                    AND oji_inner.status >= 3 AND oji_inner.status <> 99
                ) oji
                INNER JOIN ops_job_item_channels ojic ON oji.id = ojic.ops_job_item_id
                INNER JOIN vend_channels vc ON ojic.vend_channel_id = vc.id
                INNER JOIN ops_jobs oj ON oji.ops_job_id = oj.id
                WHERE oji.rn = 2 AND oj.date < CURDATE() + INTERVAL 1 DAY
                GROUP BY oji.customer_id
            ");
        });

        // ── 14. Conditional sort subquery: next_ops_jobs ─────────────────────
        $this->bench('Sort subquery: next_ops_jobs — pending jobs (all customers)', function () {
            return DB::select("
                SELECT oji.customer_id, oji.cash_amount,
                    SUM(ojic.picked_qty * vc.amount) AS amount,
                    SUM(ojic.picked_qty) AS count
                FROM ops_job_items oji
                INNER JOIN (
                    SELECT customer_id, MAX(created_at) AS min_created_at
                    FROM ops_job_items
                    WHERE status < 3
                    GROUP BY customer_id
                ) next_job ON next_job.customer_id = oji.customer_id AND oji.created_at = next_job.min_created_at
                INNER JOIN ops_job_item_channels ojic ON oji.id = ojic.ops_job_item_id
                INNER JOIN vend_channels vc ON ojic.vend_channel_id = vc.id
                INNER JOIN ops_jobs oj ON oji.ops_job_id = oj.id
                WHERE oji.status < 3 AND oj.date >= CURDATE()
                GROUP BY oji.customer_id
            ");
        });

        // ── 15. Conditional sort subquery: last_thirty_days_stock_in ─────────
        // Drive from ops_jobs (date filter via idx_oj_date), then filter ops_job_items
        // to operator's customers via the pre-fetched IN list inline on the join.
        $this->bench('Sort subquery: last_thirty_days_stock_in — ops_jobs-first + pre-fetched IN list (' . count($benchCustomerIds) . ' customers)', function () use ($benchCustomerIn) {
            return DB::select("
                SELECT SUM(ojic.actual_qty) AS qty,
                       SUM(ojic.actual_qty * vc.amount) AS amount,
                       oji.customer_id
                FROM ops_jobs oj
                INNER JOIN ops_job_items oji ON oji.ops_job_id = oj.id
                    AND oji.customer_id IN ({$benchCustomerIn})
                    AND oji.status >= 3 AND oji.status <> 99
                INNER JOIN ops_job_item_channels ojic ON ojic.ops_job_item_id = oji.id
                INNER JOIN vend_channels vc ON ojic.vend_channel_id = vc.id
                WHERE oj.date BETWEEN CURDATE() - INTERVAL 29 DAY AND CURDATE()
                GROUP BY oji.customer_id
            ");
        });

        // ── 16. Products dropdown (operator-scoped, short-TTL cache) ──────────
        $this->bench('Products dropdown (operator-scoped, cached 5min)', function () use ($operatorIds, $noCache) {
            sort($operatorIds);
            // Mirror VendController's version-stamped key (OptionCacheBuster).
            $cacheKey = 'customer_product_options_v' . \App\Support\OptionCacheBuster::productOptionsVersion()
                . '_' . implode('_', $operatorIds);
            if ($noCache) {
                Cache::forget($cacheKey);
            }
            return Cache::remember($cacheKey, 300, function () use ($operatorIds) {
                return Product::query()
                    ->with(['thumbnail', 'isAvailableUpdatedBy'])
                    ->whereIn('operator_id', $operatorIds)
                    ->select('id', 'code', 'desc', 'name', 'is_available', 'is_available_updated_at', 'is_available_updated_by')
                    ->where('is_active', true)
                    ->where('is_inventory', true)
                    ->orderBy('code')
                    ->get();
            });
        });

        $this->newLine();
        $this->info('Done.');
        $this->info('Reds above (>5s) are your bottlenecks. Share this output for targeted optimization.');
        $this->newLine();
        $this->comment('Hint — sort keys and the subquery they activate:');
        $this->comment('  vc           → thirty_days_over_full_load_ratio, total_stock_amount, total_full_load_amount');
        $this->comment('  vc_cost      → total_stock_cost');
        $this->comment('  vc_stock     → actual_stock_in_value, actual_stock_in_qty');
        $this->comment('  last_ops     → last_ops_job_acc_total_amount/count/amount/cash_amount/count');
        $this->comment('  second_ops   → last_second_ops_job_*');
        $this->comment('  next_ops     → next_ops_job_amount/cash_amount/count');
        $this->comment('  thirty_stock → last_thirty_days_stock_in_amount/qty, thirty_days_stock_in_delta_*');
    }

    /**
     * Run $fn, print timing + row count, and optionally capture the result.
     */
    private function bench(string $label, callable $fn, mixed &$capture = null): void
    {
        $this->output->write("  {$label} ... ");
        $start = microtime(true);

        try {
            $result = $fn();
            $ms     = round((microtime(true) - $start) * 1000);
            $rows   = is_countable($result) ? count($result) : '?';
            $status = $ms > 5000 ? '🔴' : ($ms > 1000 ? '🟡' : '🟢');
            $this->line("{$status} {$ms}ms ({$rows} rows)");
        } catch (\Throwable $e) {
            $ms = round((microtime(true) - $start) * 1000);
            $this->line("🔴 FAILED after {$ms}ms — {$e->getMessage()}");
            $result = [];
        }

        if (func_num_args() >= 3) {
            $capture = $result;
        }
    }
}
