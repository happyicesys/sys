<?php

namespace App\Console\Commands;

use App\Models\Month;
use App\Models\Operator;
use App\Models\VendRecord;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Run each dashboard sub-query in isolation with timing.
 * Safe to run in production — read-only, no side effects.
 *
 * Usage:
 *   php artisan dashboard:debug
 *   php artisan dashboard:debug --operator=1          # filter by operator ID
 *   php artisan dashboard:debug --month-year=2026-04  # specific month
 *   php artisan dashboard:debug --no-cache            # skip cache, force DB hits
 */
class DebugDashboardPerformance extends Command
{
    protected $signature = 'dashboard:debug
                            {--operator= : Operator ID to filter by (leave blank = use first operator)}
                            {--month-year= : Month/year in Y-m format (default: current month)}
                            {--no-cache : Bypass cache and hit DB directly}';

    protected $description = 'Time each dashboard sub-query individually to find the production bottleneck';

    public function handle(): void
    {
        $operatorId = $this->option('operator');
        $monthYear  = $this->option('month-year') ?? Carbon::today()->format('Y-m');
        $noCache    = $this->option('no-cache');

        // Resolve operator
        if ($operatorId) {
            $operator = Operator::find($operatorId);
        } else {
            $operator = Operator::first();
        }

        if (! $operator) {
            $this->error('No operator found. Pass --operator=<id>');
            return;
        }

        $this->info("Operator : {$operator->name} (#{$operator->id})");
        $this->info("Month    : {$monthYear}");
        $this->info("Cache    : " . ($noCache ? 'BYPASSED' : 'enabled'));
        $this->newLine();

        if ($noCache) {
            Cache::forget('testing_vend_ids');
            Cache::forget('exclude_vend_ids_for_active_machine');
        }

        // Build a minimal fake request that mimics what the dashboard receives
        $request = Request::create('/dashboard/performance', 'GET', [
            'autoload'   => true,
            'month_year' => $monthYear,
            'operators'  => [$operator->id],
        ]);

        // ── 1. testingVendIds ─────────────────────────────────────────────
        $this->bench('testingVendIds (vends scan)', function () use ($noCache) {
            if ($noCache) {
                return DB::table('vends')->where('is_testing', true)->pluck('id')->toArray();
            }
            return Cache::remember('testing_vend_ids', 300, function () {
                return DB::table('vends')->where('is_testing', true)->pluck('id')->toArray();
            });
        }, $testingVendIds);

        // ── 2. getDayGraph ────────────────────────────────────────────────
        $this->bench('getDayGraph (VendRecord + today txn)', function () use ($request, $testingVendIds, $monthYear) {
            $baseDate      = Carbon::createFromFormat('Y-m', $monthYear);
            $day_date_from = $baseDate->copy()->startOfMonth();
            $day_date_to   = $baseDate->copy()->endOfMonth();

            return VendRecord::query()
                ->whereBetween('date', [$day_date_from->copy()->subMonth()->startOfDay(), $day_date_to->copy()->endOfDay()])
                ->when($request->operators, fn($q) => $q->whereIn('operator_id', $request->operators))
                ->whereNotIn('vend_id', $testingVendIds)
                ->groupBy('date')
                ->select(
                    DB::raw('MONTH(date) as month'),
                    DB::raw('DATE(date) as date'),
                    DB::raw('DAY(date) as day'),
                    DB::raw('SUM(total_amount) as amount'),
                    DB::raw('SUM(total_count) as count')
                )
                ->orderBy('date', 'asc')
                ->get();
        });

        // ── 3. getProductGraph (VendTransaction UNION) ────────────────────
        $this->bench('getProductGraph (vend_transactions union)', function () use ($request, $testingVendIds) {
            $from = Carbon::today()->subDays(6)->startOfDay();
            $to   = Carbon::today()->endOfDay();

            return DB::table('vend_transactions')
                ->when($request->operators, fn($q) => $q->whereIn('operator_id', $request->operators))
                ->whereBetween('transaction_datetime', [$from, $to])
                ->whereNotIn('vend_id', $testingVendIds)
                ->select('product_id', DB::raw('SUM(qty) as total_count'), DB::raw('SUM(amount) as total_amount'))
                ->groupBy('product_id')
                ->orderByDesc('total_count')
                ->limit(10)
                ->get();
        });

        // ── 4. getBestPerformer ───────────────────────────────────────────
        $this->bench('getBestPerformer (vend_records 30d GROUP BY vend)', function () use ($request, $testingVendIds) {
            return VendRecord::query()
                ->when($request->operators, fn($q) => $q->whereIn('operator_id', $request->operators))
                ->whereBetween('date', [Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay()])
                ->whereNotIn('vend_id', $testingVendIds)
                ->groupBy('vend_records.vend_id')
                ->select('vend_records.vend_id', DB::raw('SUM(total_amount) as amount'))
                ->orderBy('amount', 'desc')
                ->limit(20)
                ->get();
        });

        // ── 5. getWorstPerformer ──────────────────────────────────────────
        $this->bench('getWorstPerformer (vend_records 30d GROUP BY vend)', function () use ($request, $testingVendIds) {
            return VendRecord::query()
                ->when($request->operators, fn($q) => $q->whereIn('operator_id', $request->operators))
                ->whereBetween('date', [Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay()])
                ->whereNotIn('vend_id', $testingVendIds)
                ->groupBy('vend_records.vend_id')
                ->select('vend_records.vend_id', DB::raw('SUM(total_amount) as amount'))
                ->orderBy('amount', 'asc')
                ->limit(20)
                ->get();
        });

        // ── 6. getVendCount ───────────────────────────────────────────────
        $this->bench('getVendCount (count yesterday\'s records)', function () use ($request, $testingVendIds, $noCache) {
            // whereBetween instead of whereDate(): DATE(col) = X disables index, range does not.
            $run = fn() => VendRecord::query()
                ->from(DB::raw('`vend_records` USE INDEX (idx_operator_date_vend)'))
                ->when($request->operators, fn($q) => $q->whereIn('operator_id', $request->operators))
                ->whereBetween('date', [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()])
                ->whereNotIn('vend_id', $testingVendIds)
                ->count();

            return $noCache ? $run() : Cache::remember('dbg_vend_count', 300, $run);
        });

        // ── 7. getMonthGraphData ──────────────────────────────────────────
        $this->bench('getMonthGraphData (2-year GROUP BY month, idx_operator_year_month)', function () use ($request, $testingVendIds, $monthYear, $noCache) {
            $baseDate = Carbon::createFromFormat('Y-m', $monthYear);
            $thisYear = $baseDate->copy()->endOfYear();
            $lastYear = $baseDate->copy()->subYear()->startOfYear();

            // idx_vr_monthly_summary (operator_id, year, month, vend_id, total_amount, total_count)
            // is a covering index — entire query resolves with zero heap reads.
            $run = fn() => VendRecord::query()
                ->from(DB::raw('`vend_records` USE INDEX (idx_vr_monthly_summary)'))
                ->whereBetween('year', [$lastYear->year, $thisYear->year])
                ->when($request->operators, fn($q) => $q->whereIn('operator_id', $request->operators))
                ->whereNotIn('vend_id', $testingVendIds)
                ->groupBy('year', 'month')
                ->select(DB::raw('month'), DB::raw('year'), DB::raw('SUM(total_amount) as amount'))
                ->get();

            return $noCache ? $run() : Cache::remember('dbg_month_graph', 300, $run);
        });

        // ── 8. getActiveMachineGraphData ──────────────────────────────────
        $this->bench('getActiveMachineGraphData (2-year COUNT DISTINCT, idx_operator_year_month)', function () use ($request, $monthYear, $noCache) {
            $baseDate = Carbon::createFromFormat('Y-m', $monthYear);
            $thisYear = $baseDate->copy()->endOfYear();
            $lastYear = $baseDate->copy()->subYear()->startOfYear();

            $excludeVendIds = $noCache
                ? DB::table('vends')->where(fn($q) => $q->where('is_testing', true)->orWhereNull('customer_id'))->pluck('id')->toArray()
                : Cache::remember('exclude_vend_ids_for_active_machine', 300, fn() =>
                    DB::table('vends')->where(fn($q) => $q->where('is_testing', true)->orWhereNull('customer_id'))->pluck('id')->toArray()
                  );

            // idx_vr_monthly_summary covers vend_id — COUNT DISTINCT and NOT IN resolve with zero heap reads.
            return DB::table(DB::raw('`vend_records` USE INDEX (idx_vr_monthly_summary)'))
                ->selectRaw('year, month, COUNT(DISTINCT vend_id) as count')
                ->whereBetween('year', [$lastYear->year, $thisYear->year])
                ->whereNotIn('vend_id', $excludeVendIds)
                ->when($request->operators, fn($q) => $q->whereIn('operator_id', $request->operators))
                ->groupBy('year', 'month')
                ->orderBy('year')->orderBy('month')
                ->get();
        });

        // ── 9. getMonthlyAnalytics (getMonthlySalesQuery) ─────────────────
        $this->bench('getMonthlyAnalytics (double-join subquery, full year)', function () use ($request, $monthYear, $noCache) {
            $baseDate      = Carbon::createFromFormat('Y-m', $monthYear);
            $monthlyDateFrom = $baseDate->copy()->startOfYear()->startOfDay();
            $monthlyDateTo   = $baseDate->copy()->endOfYear()->endOfDay();

            $run = fn() => VendRecord::query()
                ->selectRaw('vend_records.month')
                ->selectRaw('SUM(vend_records.total_amount) as amount')
                ->selectRaw('COUNT(DISTINCT vend_records.vend_id) as vend_count')
                ->leftJoin('vends as v2', fn($j) => $j->on('vend_records.vend_id', '=', 'v2.id')->where('v2.is_testing', true))
                ->leftJoin('location_types', 'vend_records.location_type_id', '=', 'location_types.id')
                ->whereBetween('vend_records.date', [$monthlyDateFrom, $monthlyDateTo])
                ->whereNull('v2.id')
                ->when($request->operators, fn($q) => $q->whereIn('vend_records.operator_id', $request->operators))
                ->selectRaw('location_types.id as id')
                ->selectRaw('location_types.name as name')
                ->groupBy('location_types.id', 'vend_records.month')
                ->orderBy('location_types.name', 'asc')
                ->get();

            return $noCache ? $run() : Cache::remember('dbg_monthly_analytics', 300, $run);
        });

        // ── 10. getSalesComparisonGraph ───────────────────────────────────
        $this->bench('getSalesComparisonGraph (6-month OR query)', function () use ($request, $testingVendIds, $monthYear, $noCache) {
            $baseDate = Carbon::createFromFormat('Y-m', $monthYear)->startOfMonth();
            $periods  = [
                $baseDate->copy(),
                $baseDate->copy()->subMonth(),
                $baseDate->copy()->addMonth(),
                $baseDate->copy()->subYear(),
                $baseDate->copy()->subYear()->subMonth(),
                $baseDate->copy()->subYear()->addMonth(),
            ];

            // whereBetween per period instead of whereYear()/whereMonth(): function calls on
            // the date column prevent index use. A plain range predicate can use the date index.
            $run = fn() => VendRecord::query()
                ->from(DB::raw('`vend_records` USE INDEX (idx_operator_date_vend)'))
                ->when($request->operators, fn($q) => $q->whereIn('operator_id', $request->operators))
                ->whereNotIn('vend_id', $testingVendIds)
                ->select(DB::raw('SUM(total_amount) as amount'), DB::raw('DAY(date) as day'), DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'))
                ->groupBy('year', 'month', 'day')
                ->where(fn($q) => collect($periods)->each(fn($d) =>
                    $q->orWhereBetween('date', [$d->copy()->startOfMonth()->startOfDay(), $d->copy()->endOfMonth()->endOfDay()])
                ))
                ->get();

            return $noCache ? $run() : Cache::remember('dbg_sales_comparison', 300, $run);
        });

        $this->newLine();
        $this->info('Done. The slowest line above is your production bottleneck.');
        $this->info('Share the output and the fix can be targeted to that query.');
    }

    /**
     * Run $fn, print timing + row count, and optionally capture the result.
     */
    private function bench(string $label, callable $fn, mixed &$capture = null): void
    {
        $this->output->write("  {$label} ... ");
        $start   = microtime(true);

        try {
            $result  = $fn();
            $ms      = round((microtime(true) - $start) * 1000);
            $rows    = is_countable($result) ? count($result) : '?';
            $status  = $ms > 5000 ? '🔴' : ($ms > 1000 ? '🟡' : '🟢');
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
