<?php

namespace App\Services;

use App\Models\GpMetric;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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

        $singleRevenueExpression = 'COALESCE(vend_transactions.revenue, vend_transactions.amount, 0)';
        $singleUnitCostExpression = 'COALESCE(vend_transactions.unit_cost, 0)';
        $singleGrossProfitExpression = '(' . $singleRevenueExpression . ' - ' . $singleUnitCostExpression . ')';
        $singleCountExpression = 'CASE WHEN vend_transactions.success_qty IS NULL OR vend_transactions.success_qty = 0 THEN 1 ELSE vend_transactions.success_qty END';

        $single = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('vend_channels', 'vend_transactions.vend_channel_id', '=', 'vend_channels.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->whereBetween('vend_transactions.created_at', [$start, $end])
            ->where(function ($query) {
                $query->where('vend_transactions.is_multiple', false)
                    ->orWhereNotExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('vend_transaction_items')
                            ->whereColumn('vend_transaction_items.vend_transaction_id', 'vend_transactions.id');
                    });
            })
            ->where(function ($query) {
                $query->whereIn('vend_transactions.error_code_normalized', [0, 6])
                    ->orWhereNull('vend_transactions.error_code_normalized');
            })
            ->selectRaw('DATE(vend_transactions.created_at) as txn_date')
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
            ->selectRaw('SUM(' . $singleRevenueExpression . ') as revenue_cents')
            ->selectRaw('SUM(' . $singleGrossProfitExpression . ') as gross_profit_cents')
            ->selectRaw('SUM(' . $singleUnitCostExpression . ') as unit_cost_cents')
            ->groupBy([
                DB::raw('DATE(vend_transactions.created_at)'),
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

        $multiRevenueExpression = 'COALESCE(
                vend_channels.amount,
                ROUND(
                    CASE
                        WHEN vend_transactions.success_qty IS NOT NULL AND vend_transactions.success_qty > 0 THEN ' . $singleRevenueExpression . ' / NULLIF(vend_transactions.success_qty, 0)
                        WHEN vend_transactions.qty IS NOT NULL AND vend_transactions.qty > 0 THEN ' . $singleRevenueExpression . ' / NULLIF(vend_transactions.qty, 0)
                        ELSE 0
                    END
                ),
                0
            )';

        $multiUnitCostExpression = 'ROUND(COALESCE(vend_transaction_items.unit_cost, 0) * 100)';
        $multiGrossProfitExpression = '(' . $multiRevenueExpression . ' - ' . $multiUnitCostExpression . ')';

        $multi = DB::table('vend_transaction_items')
            ->join('vend_transactions', 'vend_transaction_items.vend_transaction_id', '=', 'vend_transactions.id')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('vend_channels', 'vend_transaction_items.vend_channel_id', '=', 'vend_channels.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->whereBetween('vend_transactions.created_at', [$start, $end])
            ->where('vend_transactions.is_multiple', true)
            ->whereIn('vend_transaction_items.vend_channel_error_code', [0, 6])
            ->selectRaw('DATE(vend_transactions.created_at) as txn_date')
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
            ->selectRaw('COUNT(*) as sale_count')
            ->selectRaw('COUNT(DISTINCT vend_transaction_items.vend_transaction_id) as transaction_count')
            ->selectRaw('SUM(' . $multiRevenueExpression . ') as revenue_cents')
            ->selectRaw('SUM(' . $multiGrossProfitExpression . ') as gross_profit_cents')
            ->selectRaw('SUM(' . $multiUnitCostExpression . ') as unit_cost_cents')
            ->groupBy([
                DB::raw('DATE(vend_transactions.created_at)'),
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

        $query->chunk($chunkSize, function ($rows) use ($now) {
            $payload = $rows->map(function ($row) use ($now) {
                $data = get_object_vars($row);
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                return $data;
            })->all();

            if (!empty($payload)) {
                GpMetric::query()->insert($payload);
            }
        });
    }
}
