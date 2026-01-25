<?php

namespace App\Services;

use App\Models\VendTransaction;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class VendTransactionSalesAggregator
{
    /**
     * Build an aggregated query that returns total sold item counts and amounts per product.
     *
     * @param  Carbon  $start
     * @param  Carbon  $end
     * @param  Closure|null  $applyFilter  Receives an Eloquent builder to allow additional filtering (e.g. scopes).
     * @return Builder
     */
    public static function productTotals(Carbon $start, Carbon $end, ?Closure $applyFilter = null): Builder
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $singleQuery = VendTransaction::query()
            ->whereBetween('vend_transactions.transaction_datetime', [$start, $end]);

        if ($applyFilter) {
            $applyFilter($singleQuery);
        }

        self::clearOrders($singleQuery);

        $singleQuery
            ->leftJoin('vend_channels as single_vc', 'single_vc.id', '=', 'vend_transactions.vend_channel_id')
            ->where(function (EloquentBuilder $query) {
                $query->where('vend_transactions.is_multiple', false)
                    ->orWhereNull('vend_transactions.is_multiple');
            })
            ->where(function (EloquentBuilder $query) {
                $query->whereNull('vend_transactions.error_code_normalized')
                    ->orWhereIn('vend_transactions.error_code_normalized', [0, 6]);
            })
            ->selectRaw('COALESCE(vend_transactions.product_id, single_vc.product_id) as product_id')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(vend_transactions.amount) as total_amount')
            ->groupBy('product_id');

        $single = $singleQuery->toBase();

        $multiQuery = VendTransaction::query()
            ->whereBetween('vend_transactions.transaction_datetime', [$start, $end])
            // ->where('vend_transactions.amount', '>', 0)
            ->where('vend_transactions.is_multiple', true);

        if ($applyFilter) {
            $applyFilter($multiQuery);
        }

        self::clearOrders($multiQuery);

        $multiQuery
            ->join('vend_transaction_items as vti', 'vti.vend_transaction_id', '=', 'vend_transactions.id')
            ->leftJoin('vend_channels as multi_vc', 'multi_vc.id', '=', 'vti.vend_channel_id')
            ->selectRaw('COALESCE(vti.product_id, multi_vc.product_id) as product_id')
            ->selectRaw('SUM(CASE WHEN vti.vend_channel_error_code IN (0, 6) OR vti.vend_channel_error_code IS NULL THEN 1 ELSE 0 END) as total_count')
            ->selectRaw('SUM(CASE WHEN vti.vend_channel_error_code IN (0, 6) OR vti.vend_channel_error_code IS NULL THEN COALESCE(vti.unit_price_amount, 0) ELSE 0 END) as total_amount')
            ->groupBy('product_id');

        $multi = $multiQuery->toBase();

        $union = $single->unionAll($multi);

        return DB::query()
            ->fromSub($union, 'product_sales')
            ->selectRaw('product_id, SUM(total_count) as total_count, SUM(total_amount) as total_amount')
            ->whereNotNull('product_id')
            ->groupBy('product_id');
    }

    /**
     * Remove ordering clauses that could break unions/subqueries.
     */
    private static function clearOrders(EloquentBuilder $builder): void
    {
        $builder->getQuery()->orders = null;
        $builder->getQuery()->unionOrders = null;
    }
}

