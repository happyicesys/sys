<?php

namespace App\Services;

use App\Models\GpMetric;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GpMetricsAggregator
{
    /**
     * Build a query that aggregates vend transaction data into the gp_metrics shape.
     *
     * @param  Carbon  $start
     * @param  Carbon  $end
     * @return Builder
     */
    public static function buildRawQuery(Carbon $start, Carbon $end): Builder
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $transactionDatetimeColumn = 'COALESCE(vend_transactions.transaction_datetime, vend_transactions.created_at)';
        $transactionDateExpression = "DATE($transactionDatetimeColumn)";

        $applyDateRange = function ($query) use ($start, $end) {
            $query->whereBetween('vend_transactions.transaction_datetime', [$start, $end])
                ->orWhere(function ($or) use ($start, $end) {
                    $or->whereNull('vend_transactions.transaction_datetime')
                        ->whereBetween('vend_transactions.created_at', [$start, $end]);
                });
        };

        $singleAmountExpression = 'COALESCE(vend_transactions.amount, 0)';
        $singleRevenueExpression = 'COALESCE(vend_transactions.revenue, vend_transactions.amount, 0)';
        $singleUnitCostExpression = 'COALESCE(vend_transactions.unit_cost, 0)';
        $singleGrossProfitExpression = '(' . $singleRevenueExpression . ' - ' . $singleUnitCostExpression . ')';
        $singleCountExpression = 'COALESCE(vend_transactions.qty, 1)';

        $single = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('vend_channel_errors', 'vend_transactions.vend_channel_error_id', '=', 'vend_channel_errors.id')
            ->leftJoin('vend_channels', 'vend_transactions.vend_channel_id', '=', 'vend_channels.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->where(function ($query) use ($applyDateRange) {
                $applyDateRange($query);
            })
            ->where('vend_transactions.amount', '>', 0)
            ->where(function ($query) {
                $query->where('vend_transactions.is_multiple', false)
                    ->orWhereNotExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('vend_transaction_items')
                            ->whereColumn('vend_transaction_items.vend_transaction_id', 'vend_transactions.id');
                    });
            })
            ->selectRaw("$transactionDateExpression as txn_date")
            ->selectRaw('vend_transactions.operator_id as operator_id')
            ->selectRaw('vend_transactions.vend_id as vend_id')
            ->selectRaw('vend_transactions.customer_id as customer_id')
            ->selectRaw('customers.category_id as category_id')
            ->selectRaw('category_groups.id as category_group_id')
            ->selectRaw('customers.location_type_id as customer_location_type_id')
            ->selectRaw('vend_transactions.location_type_id as transaction_location_type_id')
            ->selectRaw('vends.vend_prefix_id as vend_prefix_id')
            ->selectRaw('vend_transactions.vend_contract_id as vend_contract_id')
            ->selectRaw('vend_transactions.vend_model_id as vend_model_id')
            ->selectRaw('COALESCE(vend_transactions.product_id, vend_channels.product_id) as product_id')
            ->selectRaw('0 as is_multiple')
            ->selectRaw('CASE WHEN customers.id IS NULL THEN 0 ELSE 1 END as is_binded_customer')
            ->selectRaw('SUM(' . $singleCountExpression . ') as sale_count')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw("SUM(CASE WHEN vend_transactions.vend_channel_error_id IS NULL OR vend_channel_errors.code IN (0, 6) THEN {$singleCountExpression} ELSE 0 END) as success_count")
            ->selectRaw("SUM(CASE WHEN vend_transactions.vend_channel_error_id IS NOT NULL AND (vend_channel_errors.code IS NULL OR vend_channel_errors.code NOT IN (0, 6)) THEN {$singleCountExpression} ELSE 0 END) as error_count")
            ->selectRaw("SUM(CASE WHEN vend_transactions.vend_channel_error_id IS NULL OR vend_channel_errors.code IN (0, 6) THEN $singleAmountExpression ELSE 0 END) as amount_cents")
            ->selectRaw("SUM(CASE WHEN vend_transactions.vend_channel_error_id IS NULL OR vend_channel_errors.code IN (0, 6) THEN ($singleRevenueExpression) ELSE 0 END) as revenue_cents")
            ->selectRaw("SUM(CASE WHEN vend_transactions.vend_channel_error_id IS NULL OR vend_channel_errors.code IN (0, 6) THEN ($singleGrossProfitExpression) ELSE 0 END) as gross_profit_cents")
            ->selectRaw('SUM(' . $singleUnitCostExpression . ') as unit_cost_cents')
            ->groupBy([
                DB::raw($transactionDateExpression),
                'vend_transactions.operator_id',
                'vend_transactions.vend_id',
                'vend_transactions.customer_id',
                'customers.category_id',
                'category_groups.id',
                'customers.location_type_id',
                'vend_transactions.location_type_id',
                'vends.vend_prefix_id',
                'vend_transactions.vend_contract_id',
                'vend_transactions.vend_model_id',
                DB::raw('COALESCE(vend_transactions.product_id, vend_channels.product_id)'),
                DB::raw('CASE WHEN customers.id IS NULL THEN 0 ELSE 1 END'),
            ]);

        $multiAmountExpression = 'COALESCE(vend_transaction_items.unit_price_amount, 0)';
        $multiRevenueExpression = 'ROUND(COALESCE(vend_transaction_items.unit_price_amount, 0) / (1 + COALESCE(vend_transactions.gst_vat_rate, 0) / 100))';
        $multiSub = DB::table('vend_transaction_items as vti')
            ->select(
                'vend_transaction_id',
                DB::raw('SUM(unit_price_amount) as item_sum'),
                DB::raw('COUNT(CASE WHEN unit_price_amount = 0 THEN 1 END) as zero_count'),
                DB::raw('COUNT(*) as total_count')
            )
            ->groupBy('vend_transaction_id');

        $adjustedAmountExpr = "vend_transaction_items.unit_price_amount + (CASE
            WHEN vti_sum.zero_count > 0 AND vend_transaction_items.unit_price_amount = 0 THEN (vend_transactions.amount - vti_sum.item_sum) / vti_sum.zero_count
            WHEN vti_sum.zero_count = 0 THEN (vend_transactions.amount - vti_sum.item_sum) / vti_sum.total_count
            ELSE 0 END)";

        $adjustedRevenueExpr = "COALESCE(vend_transactions.revenue, vend_transactions.amount, 0) / NULLIF(vti_sum.total_count, 0)";

        $multi = DB::table('vend_transaction_items')
            ->join('vend_transactions', 'vend_transaction_items.vend_transaction_id', '=', 'vend_transactions.id')
            ->leftJoinSub($multiSub, 'vti_sum', 'vend_transactions.id', '=', 'vti_sum.vend_transaction_id')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('vend_channels', 'vend_transaction_items.vend_channel_id', '=', 'vend_channels.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->where(function ($query) use ($applyDateRange) {
                $applyDateRange($query);
            })
            ->where('vend_transactions.amount', '>', 0)
            ->where('vend_transactions.is_multiple', true)
            ->selectRaw("$transactionDateExpression as txn_date")
            ->selectRaw('vend_transactions.operator_id as operator_id')
            ->selectRaw('vend_transactions.vend_id as vend_id')
            ->selectRaw('vend_transactions.customer_id as customer_id')
            ->selectRaw('customers.category_id as category_id')
            ->selectRaw('category_groups.id as category_group_id')
            ->selectRaw('customers.location_type_id as customer_location_type_id')
            ->selectRaw('vend_transactions.location_type_id as transaction_location_type_id')
            ->selectRaw('vends.vend_prefix_id as vend_prefix_id')
            ->selectRaw('vend_transactions.vend_contract_id as vend_contract_id')
            ->selectRaw('vend_transactions.vend_model_id as vend_model_id')
            ->selectRaw('COALESCE(vend_transaction_items.product_id, vend_channels.product_id) as product_id')
            ->selectRaw('1 as is_multiple')
            ->selectRaw('CASE WHEN customers.id IS NULL THEN 0 ELSE 1 END as is_binded_customer')
            ->selectRaw('SUM(1) as sale_count')
            ->selectRaw('COUNT(DISTINCT vend_transaction_items.vend_transaction_id) as transaction_count')
            ->selectRaw('SUM(1) as success_count')
            ->selectRaw('0 as error_count')
            ->selectRaw("SUM($adjustedAmountExpr) as amount_cents")
            ->selectRaw("SUM($adjustedRevenueExpr) as revenue_cents")
            ->selectRaw("SUM($adjustedRevenueExpr - (COALESCE(vend_transaction_items.unit_cost, 0) * 100)) as gross_profit_cents")
            ->selectRaw('SUM(COALESCE(vend_transaction_items.unit_cost, 0) * 100) as unit_cost_cents')
            ->groupBy([
                DB::raw($transactionDateExpression),
                'vend_transactions.operator_id',
                'vend_transactions.vend_id',
                'vend_transactions.customer_id',
                'customers.category_id',
                'category_groups.id',
                'customers.location_type_id',
                'vend_transactions.location_type_id',
                'vends.vend_prefix_id',
                'vend_transactions.vend_contract_id',
                'vend_transactions.vend_model_id',
                DB::raw('COALESCE(vend_transaction_items.product_id, vend_channels.product_id)'),
                DB::raw('CASE WHEN customers.id IS NULL THEN 0 ELSE 1 END'),
            ]);

        $union = $single->unionAll($multi);

        return DB::query()->fromSub($union, 'metrics')
            ->select([
                'metrics.txn_date',
                'metrics.operator_id',
                'metrics.vend_id',
                'metrics.customer_id',
                'metrics.category_id',
                'metrics.category_group_id',
                'metrics.customer_location_type_id',
                'metrics.transaction_location_type_id',
                'metrics.vend_prefix_id',
                'metrics.vend_contract_id',
                'metrics.vend_model_id',
                'metrics.product_id',
                'metrics.is_multiple',
                'metrics.is_binded_customer',
                'metrics.sale_count',
                'metrics.transaction_count',
                'metrics.success_count',
                'metrics.error_count',
                'metrics.amount_cents',
                'metrics.revenue_cents',
                'metrics.gross_profit_cents',
                'metrics.unit_cost_cents',
            ]);
    }

    /**
     * Persist metrics for a specific day into the gp_metrics table.
     *
     * @param  Carbon  $day
     * @param  int     $chunkSize
     * @return void
     */
    public static function persistDay(Carbon $day, int $chunkSize = 1000): void
    {
        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        GpMetric::query()->where('txn_date', $dayStart->toDateString())->delete();

        $query = self::buildRawQuery($dayStart, $dayEnd);
        $now = now();

        $query->orderBy('txn_date')
            ->orderBy('vend_id')
            ->orderBy('product_id')
            ->chunk($chunkSize, function ($rows) use ($now, $dayStart) {
                $payload = $rows->map(function ($row) use ($now) {
                    $data = get_object_vars($row);
                    // success_count and error_count are live-only columns not stored in gp_metrics
                    unset($data['success_count'], $data['error_count']);
                    $data['created_at'] = $now;
                    $data['updated_at'] = $now;
                    return $data;
                })->all();

                if (!empty($payload)) {
                    self::insertWithRetry($payload, $dayStart->toDateString());
                }
            });
    }

    /**
     * Attempt to insert the payload, retrying on transient deadlocks.
     */
    private static function insertWithRetry(array $payload, string $date, int $maxAttempts = 5): void
    {
        $attempt = 1;
        $delayMs = 100;

        while (true) {
            try {
                GpMetric::query()->insert($payload);
                return;
            } catch (QueryException $exception) {
                if (!self::isDeadlock($exception) || $attempt >= $maxAttempts) {
                    throw $exception;
                }

                Log::warning('Retrying gp_metrics insert after deadlock', [
                    'date' => $date,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'error_code' => $exception->errorInfo[1] ?? null,
                ]);

                usleep($delayMs * 1000);
                $attempt++;
                $delayMs = min($delayMs * 2, 5000);
            }
        }
    }

    /**
     * Determine if the exception is a transient deadlock or lock timeout.
     */
    private static function isDeadlock(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($sqlState, ['40001', 'HY000'], true)
            && in_array($driverCode, [1205, 1213], true);
    }
}
