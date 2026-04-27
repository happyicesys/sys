<?php

namespace App\Console\Commands;

use App\Models\Operator;
use App\Services\GpMetricsAggregator;
use App\Traits\HasFilter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Benchmark the Sales Report > Product tab query chain in isolation.
 * Safe to run in production — read-only, no side effects.
 *
 * Usage:
 *   php artisan sales-report:debug-product
 *   php artisan sales-report:debug-product --from=2026-04-20 --to=2026-04-26
 *   php artisan sales-report:debug-product --from=2026-04-20 --to=2026-04-27   # spans today
 *   php artisan sales-report:debug-product --operator=1
 *   php artisan sales-report:debug-product --explain   # show EXPLAIN for the slow query
 */
class DebugSalesReportProductPerformance extends Command
{
    use HasFilter;

    protected $signature = 'sales-report:debug-product
                            {--from=        : date_from (YYYY-MM-DD, default: last Monday)}
                            {--to=          : date_to   (YYYY-MM-DD, default: last Sunday)}
                            {--operator=    : Operator ID (default: first operator)}
                            {--explain      : Run EXPLAIN on the final query}
                            {--per-page=30  : Rows per page for paginate step}';

    protected $description = 'Time each step of the Sales Report Product tab query to find the bottleneck';

    public function handle(): void
    {
        // ── resolve operator ─────────────────────────────────────────────────
        $operatorId = $this->option('operator');
        $operator   = $operatorId ? Operator::find($operatorId) : Operator::first();

        if (! $operator) {
            $this->error('No operator found. Pass --operator=<id>');
            return;
        }

        // ── resolve date range (default: last full week) ─────────────────────
        $fromStr = $this->option('from') ?: Carbon::today()->subDays(7)->toDateString();
        $toStr   = $this->option('to')   ?: Carbon::today()->subDay()->toDateString();

        $start = Carbon::parse($fromStr)->startOfDay();
        $end   = Carbon::parse($toStr)->endOfDay();
        $today = Carbon::today();

        $perPage = (int) ($this->option('per-page') ?: 30);

        // ── summary header ───────────────────────────────────────────────────
        $this->info("Operator  : {$operator->name} (#{$operator->id})");
        $this->info("Date from : {$start->toDateString()}");
        $this->info("Date to   : {$end->toDateString()}");
        $this->info("Today     : {$today->toDateString()}");
        $this->newLine();

        // ── fake request that mirrors what the Product tab sends ─────────────
        $fakeRequest = new Request([
            'date_from'          => $start->toDateString(),
            'date_to'            => $end->toDateString(),
            'operators'          => [$operator->id],
            'is_binded_customer' => 'true',
            'visited'            => 'true',
            'sortKey'            => 'amount',
            'sortBy'             => false,
            'autoload'           => true,
        ]);

        // ── STEP 1: gp_metrics row count for date range ──────────────────────
        $this->line('<fg=cyan>Step 1: gp_metrics row count for date range</>');
        $gpCount = 0;
        $this->bench('  COUNT(*) on gp_metrics for date range', function () use ($start, $end, &$gpCount) {
            $gpCount = DB::table('gp_metrics')
                ->whereBetween('txn_date', [$start->toDateString(), $end->toDateString()])
                ->count();
            return $gpCount;
        });

        if ($gpCount === 0) {
            $this->warn('  ⚠  gp_metrics has 0 rows for this range — historical path will return empty results.');
            $this->warn('     Run: php artisan sync:historical-sales-metrics --from=' . $start->toDateString() . ' --to=' . $end->toDateString());
        }
        $this->newLine();

        // ── STEP 2: routing decision ─────────────────────────────────────────
        $this->line('<fg=cyan>Step 2: routing decision</>');
        if ($end->lt($today)) {
            $path = 'historical → gp_metrics only';
        } elseif ($start->gte($today)) {
            $path = 'live → buildRawQuery only';
        } else {
            $path = 'mixed → gp_metrics UNION buildRawQuery';
        }
        $this->line("  Path chosen  : <fg=yellow>{$path}</>");
        $this->newLine();

        // ── STEP 3: buildHistoricalQuery count (fast path) ───────────────────
        $this->line('<fg=cyan>Step 3: buildHistoricalQuery (gp_metrics) — count *</>');
        $this->bench('  COUNT(*) via buildHistoricalQuery', function () use ($start, $end) {
            return GpMetricsAggregator::buildHistoricalQuery($start, $end)->count();
        });
        $this->newLine();

        // ── STEP 4: buildRawQuery count (slow path, for comparison) ──────────
        $this->line('<fg=cyan>Step 4: buildRawQuery (raw vend_transactions) — count * (comparison)</>');
        $this->bench('  COUNT(*) via buildRawQuery', function () use ($start, $end) {
            return GpMetricsAggregator::buildRawQuery($start, $end)->count();
        });
        $this->newLine();

        // ── STEP 5: full getSalesQuery (products) — build + count ────────────
        $this->line('<fg=cyan>Step 5: full product-tab query (with all joins + filters) — count *</>');
        $salesQuery = null;
        $this->bench('  Build + COUNT(*) on full product query', function () use ($fakeRequest, $start, $end, &$salesQuery) {
            $salesQuery = $this->buildProductSalesQuery($fakeRequest, $start, $end);
            return (clone $salesQuery)->count();
        });
        $this->newLine();

        // ── STEP 6: totals (mirrors getSalesReportTotals — fetches ALL rows) ──
        $this->line('<fg=cyan>Step 6: totals — get() all rows (mirrors getSalesReportTotals)</>');
        $totalRows = 0;
        $this->bench('  (clone query)->get() for totals', function () use ($salesQuery, &$totalRows) {
            if (! $salesQuery) return 0;
            $rows = (clone $salesQuery)->get();
            $totalRows = $rows->count();
            return $totalRows;
        });
        $this->line("  → {$totalRows} product rows in result");
        $this->newLine();

        // ── STEP 7: paginate first page ──────────────────────────────────────
        $this->line('<fg=cyan>Step 7: paginate first page</>');
        $this->bench("  ->orderBy('amount', 'desc')->paginate({$perPage})", function () use ($salesQuery, $perPage) {
            if (! $salesQuery) return 0;
            return (clone $salesQuery)
                ->orderBy('amount', 'desc')
                ->paginate($perPage);
        });
        $this->newLine();

        // ── STEP 8: EXPLAIN (optional) ───────────────────────────────────────
        if ($this->option('explain') && $salesQuery) {
            $this->line('<fg=cyan>Step 8: EXPLAIN on the product query</>');
            $sql      = $salesQuery->toSql();
            $bindings = $salesQuery->getBindings();
            $explain  = DB::select('EXPLAIN ' . $sql, $bindings);

            $this->table(
                ['id', 'select_type', 'table', 'type', 'possible_keys', 'key', 'key_len', 'ref', 'rows', 'Extra'],
                array_map(fn($r) => (array) $r, $explain)
            );
            $this->newLine();
        }

        // ── raw SQL for reference ────────────────────────────────────────────
        if ($salesQuery) {
            $this->line('<fg=cyan>Full SQL (for manual EXPLAIN in MySQL):</>');
            $sql      = $salesQuery->toSql();
            $bindings = $salesQuery->getBindings();

            // inline bindings for easy copy-paste into MySQL Workbench
            $inlined = preg_replace_callback('/\?/', function () use (&$bindings) {
                $b = array_shift($bindings);
                return is_string($b) ? "'{$b}'" : (is_null($b) ? 'NULL' : $b);
            }, $sql);

            $this->line($inlined);
        }
    }

    // ── replicate the product-tab query from ReportController ────────────────

    private function buildProductSalesQuery(Request $request, Carbon $start, Carbon $end)
    {
        $today = Carbon::today();

        if ($end->lt($today)) {
            $rawQuery = GpMetricsAggregator::buildHistoricalQuery($start, $end);
        } elseif ($start->gte($today)) {
            $rawQuery = GpMetricsAggregator::buildRawQuery($start, $end);
        } else {
            $historical = GpMetricsAggregator::buildHistoricalQuery($start, $today->copy()->subDay());
            $live       = GpMetricsAggregator::buildRawQuery($today, $end);
            $rawQuery   = $historical->unionAll($live);
        }

        $dataset = DB::query()->fromSub($rawQuery, 'gm');

        $query = $dataset
            ->leftJoin('vends', 'gm.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'gm.customer_id', '=', 'customers.id')
            ->leftJoin('products', 'gm.product_id', '=', 'products.id')
            ->leftJoin('categories', 'gm.category_id', '=', 'categories.id')
            ->leftJoin('vend_prefixes', 'gm.vend_prefix_id', '=', 'vend_prefixes.id')
            ->leftJoin('vend_prefixes as current_vend_prefixes', 'vends.vend_prefix_id', '=', 'current_vend_prefixes.id')
            ->leftJoin('product_mappings', 'vends.product_mapping_id', '=', 'product_mappings.id')
            ->leftJoin('operators', 'operators.id', '=', 'gm.operator_id')
            ->leftJoin('vend_models', 'vend_models.id', '=', 'gm.vend_model_id')
            ->leftJoin('vend_models as current_vend_models', 'vends.vend_model_id', '=', 'current_vend_models.id')
            ->leftJoin('location_types', 'location_types.id', '=', 'gm.transaction_location_type_id')
            ->leftJoin('categories as customer_categories', 'customers.category_id', '=', 'customer_categories.id');

        $query = $this->filterGpMetricsReport($query, $request);

        // operator scope
        if ($request->operators && ! in_array('all', (array) $request->operators, true)) {
            $query->whereIn('gm.operator_id', (array) $request->operators);
        }

        $query
            ->selectRaw('gm.product_id as id')
            ->selectRaw('MAX(products.code) as code')
            ->selectRaw('MAX(products.name) as name')
            ->selectRaw('SUM(gm.error_count) AS error_count')
            ->selectRaw('SUM(gm.error_count_no_4_5) AS error_count_no_4_5')
            ->selectRaw('SUM(gm.error_count_4_5) AS error_count_4_5')
            ->selectRaw('SUM(gm.sale_count) AS count')
            ->selectRaw('SUM(ROUND(gm.amount_cents)) AS amount')
            ->groupBy('id');

        return $query;
    }

    // ── benchmark helper (same style as DebugTransactionIndexPerformance) ────

    private function bench(string $label, callable $fn): void
    {
        $start  = microtime(true);
        $result = $fn();
        $ms     = round((microtime(true) - $start) * 1000);

        $rows = match (true) {
            $result instanceof \Illuminate\Support\Collection           => $result->count(),
            $result instanceof \Illuminate\Pagination\LengthAwarePaginator => $result->total(),
            is_array($result)                                           => count($result),
            is_int($result)                                             => $result,
            default                                                     => '?',
        };

        $icon = match (true) {
            $ms < 1000  => '🟢',
            $ms < 5000  => '🟡',
            default     => '🔴',
        };

        $this->line("{$label} ... {$icon} {$ms}ms (result: {$rows})");
    }
}
