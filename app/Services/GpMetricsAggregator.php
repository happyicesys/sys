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
            ->selectRaw("SUM(CASE WHEN vend_transactions.vend_channel_error_id IS NOT NULL AND (vend_channel_errors.code IS NULL OR vend_channel_errors.code NOT IN (0, 4, 5, 6)) THEN {$singleCountExpression} ELSE 0 END) as error_count_no_4_5")
            ->selectRaw("SUM(CASE WHEN vend_transactions.vend_channel_error_id IS NOT NULL AND vend_channel_errors.code IN (4, 5) THEN {$singleCountExpression} ELSE 0 END) as error_count_4_5")
            ->selectRaw("SUM(CASE WHEN vend_transactions.vend_channel_error_id IS NULL OR vend_channel_errors.code IN (0, 6) THEN $singleAmountExpression ELSE 0 END) as amount_cents")
            ->selectRaw("SUM(CASE WHEN vend_transactions.vend_channel_error_id IS NULL OR vend_channel_errors.code IN (0, 6) THEN $singleAmountExpression ELSE 0 END) as txn_amount_cents")
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

        // Per-(transaction, product) item count — used to attribute the full transaction amount
        // exactly once per transaction per product, regardless of how many items of that product
        // are in the basket. This ensures txn_amount_cents matches the transaction page totals.
        $productCountSub = DB::table('vend_transaction_items as pc_vti')
            ->leftJoin('vend_channels as pc_vc', 'pc_vc.id', '=', 'pc_vti.vend_channel_id')
            ->select(
                'pc_vti.vend_transaction_id',
                DB::raw('COALESCE(pc_vti.product_id, pc_vc.product_id) as product_id'),
                DB::raw('COUNT(*) as product_item_count')
            )
            ->groupBy('pc_vti.vend_transaction_id', DB::raw('COALESCE(pc_vti.product_id, pc_vc.product_id)'));

        $multi = DB::table('vend_transaction_items')
            ->join('vend_transactions', 'vend_transaction_items.vend_transaction_id', '=', 'vend_transactions.id')
            ->leftJoinSub($multiSub, 'vti_sum', 'vend_transactions.id', '=', 'vti_sum.vend_transaction_id')
            ->leftJoin('vend_channels', 'vend_transaction_items.vend_channel_id', '=', 'vend_channels.id')
            ->leftJoinSub($productCountSub, 'pcs_count', function ($join) {
                $join->on('pcs_count.vend_transaction_id', '=', 'vend_transactions.id')
                     ->on('pcs_count.product_id', '=', DB::raw('COALESCE(vend_transaction_items.product_id, vend_channels.product_id)'));
            })
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
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
            // Per-item success/error split for multi-purchase baskets. We rely on
            // vend_transaction_items.vend_channel_error_code (the int column populated at write
            // time) rather than joining vend_channel_errors via the FK, because some items have
            // the error code set without a resolved FK row — joining would silently treat those
            // failures as success. This matches StoreVendProductRecords' aggregation logic.
            ->selectRaw("SUM(CASE WHEN vend_transaction_items.vend_channel_error_code IS NULL OR vend_transaction_items.vend_channel_error_code IN (0, 6) THEN 1 ELSE 0 END) as success_count")
            ->selectRaw("SUM(CASE WHEN vend_transaction_items.vend_channel_error_code IS NOT NULL AND vend_transaction_items.vend_channel_error_code NOT IN (0, 6) THEN 1 ELSE 0 END) as error_count")
            ->selectRaw("SUM(CASE WHEN vend_transaction_items.vend_channel_error_code IS NOT NULL AND vend_transaction_items.vend_channel_error_code NOT IN (0, 4, 5, 6) THEN 1 ELSE 0 END) as error_count_no_4_5")
            ->selectRaw("SUM(CASE WHEN vend_transaction_items.vend_channel_error_code IN (4, 5) THEN 1 ELSE 0 END) as error_count_4_5")
            ->selectRaw("SUM($adjustedAmountExpr) as amount_cents")
            // txn_amount_cents: full transaction amount counted exactly once per transaction per product.
            // Dividing vend_transactions.amount by the number of same-product items in each transaction,
            // then summing, attributes the full basket total once per transaction — matching the
            // transaction page which also sums vend_transactions.amount per matching transaction.
            ->selectRaw('ROUND(SUM(vend_transactions.amount / NULLIF(pcs_count.product_item_count, 0))) as txn_amount_cents')
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
                'metrics.error_count_no_4_5',
                'metrics.error_count_4_5',
                'metrics.amount_cents',
                'metrics.txn_amount_cents',
                'metrics.revenue_cents',
                'metrics.gross_profit_cents',
                'metrics.unit_cost_cents',
            ]);
    }

    /**
     * Build a query from the pre-aggregated gp_metrics table for historical (past) dates.
     * Much faster than buildRawQuery() since it reads a small, indexed, pre-aggregated table
     * instead of scanning raw vend_transactions.
     *
     * Returns the same column shape as buildRawQuery() so it can be used as a drop-in
     * replacement in baseVendTransactionMetricsQuery() for all-historical date ranges.
     *
     * @param  Carbon  $start
     * @param  Carbon  $end
     * @return Builder
     */
    public static function buildHistoricalQuery(Carbon $start, Carbon $end): Builder
    {
        return DB::table('gp_metrics')
            ->whereBetween('txn_date', [$start->toDateString(), $end->toDateString()])
            ->select([
                'txn_date',
                'operator_id',
                'vend_id',
                'customer_id',
                'category_id',
                'category_group_id',
                'customer_location_type_id',
                'transaction_location_type_id',
                'vend_prefix_id',
                'vend_contract_id',
                'vend_model_id',
                'product_id',
                'is_multiple',
                'is_binded_customer',
                'sale_count',
                'transaction_count',
                'success_count',
                'error_count',
                'error_count_no_4_5',
                'error_count_4_5',
                'amount_cents',
                DB::raw('amount_cents as txn_amount_cents'),
                'revenue_cents',
                'gross_profit_cents',
                'unit_cost_cents',
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
                    // txn_amount_cents is a live-only derived column, not stored in gp_metrics
                    unset($data['txn_amount_cents']);
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
