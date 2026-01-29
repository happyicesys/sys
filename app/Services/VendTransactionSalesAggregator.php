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
    public static function productTotals(Carbon $start, Carbon $end, ?Closure $applyFilter = null, bool $includeAll = false): Builder
    {
        // Use application timezone (usually SG/UTC+8) for "Day" boundaries, then convert to UTC for DB query
        $start = $start->copy()->setTimezone(config('app.timezone'))->startOfDay()->setTimezone('UTC');
        $end = $end->copy()->setTimezone(config('app.timezone'))->endOfDay()->setTimezone('UTC');

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
            });

        if (!$includeAll) {
            $singleQuery->where(function (EloquentBuilder $query) {
                $query->whereNull('vend_transactions.error_code_normalized')
                    ->orWhereIn('vend_transactions.error_code_normalized', [0, 6]);
            });
        }

        $singleQuery
            ->selectRaw('COALESCE(vend_transactions.product_id, single_vc.product_id) as product_id')
            ->selectRaw('SUM(COALESCE(vend_transactions.qty, 1)) as total_count')
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
            ->selectRaw('COALESCE(vti.product_id, multi_vc.product_id) as product_id');

        if ($includeAll) {
            // Count all items regardless of error status
            $multiQuery
                ->selectRaw('COUNT(*) as total_count')
                // For amount, we might still want to be careful?
                // But the caller (SyncAvgSalesQtyProducts) only ignores amount.
                // If dashboard uses this with includeAll=true, it might be wrong.
                // But dashboard uses default (false).
                // So assuming strictly for quantity here.
                // But let's keep amount calculation "safe" (only successful ones) or "gross" (all)?
                // Original code: SUM(CASE WHEN success ... THEN amount ELSE 0)
                // If includeAll, maybe we want 'attempted amount'?
                // vti.unit_price_amount
                ->selectRaw('SUM(COALESCE(vti.unit_price_amount, 0)) as total_amount');
        } else {
            $multiQuery
                ->selectRaw('SUM(CASE WHEN vti.vend_channel_error_code IN (0, 6) OR vti.vend_channel_error_code IS NULL THEN 1 ELSE 0 END) as total_count')
                ->selectRaw('SUM(CASE WHEN vti.vend_channel_error_code IN (0, 6) OR vti.vend_channel_error_code IS NULL THEN COALESCE(vti.unit_price_amount, 0) ELSE 0 END) as total_amount');
        }

        $multiQuery->groupBy('product_id');

        $multi = $multiQuery->toBase();

        $union = $single->unionAll($multi);

        return DB::query()
            ->fromSub($union, 'product_sales')
            ->selectRaw('product_id, SUM(total_count) as total_count, SUM(total_amount) as total_amount')
            ->whereNotNull('product_id')
            ->groupBy('product_id');
    }

    /**
     * Build an aggregated query that calculates the "Basket Total" for each product.
     * This mimics the "Total Qty" column on the Transactions page, which sums ALL items
     * in the transaction (basket) if the transaction is included (i.e., contains the product).
     *
     * @param  Carbon  $start
     * @param  Carbon  $end
     * @return Builder
     */
    public static function productBasketTotals(Carbon $start, Carbon $end): Builder
    {
        // Use application timezone (usually SG/UTC+8) for "Day" boundaries, then convert to UTC for DB query
        $start = $start->copy()->setTimezone(config('app.timezone'))->startOfDay()->setTimezone('UTC');
        $end = $end->copy()->setTimezone(config('app.timezone'))->endOfDay()->setTimezone('UTC');

        // 1. Strings (Single Transactions)
        // For Single transactions, Bundle/Basket Size is simply 'qty'.
        // We include ALL (success + fail) to match "Purchased" stat.
        // We reuse logic from productTotals but restrict to singles.

        $singleQuery = VendTransaction::query()
            ->whereBetween('vend_transactions.transaction_datetime', [$start, $end])
            ->leftJoin('vend_channels as single_vc', 'single_vc.id', '=', 'vend_transactions.vend_channel_id')
            ->where(function (EloquentBuilder $query) {
                $query->where('vend_transactions.is_multiple', false)
                    ->orWhereNull('vend_transactions.is_multiple');
            })
            ->selectRaw('COALESCE(vend_transactions.product_id, single_vc.product_id) as product_id')
            ->selectRaw('SUM(COALESCE(vend_transactions.qty, 1)) as total_count')
            ->groupBy('product_id')
            ->toBase();

        // 2. Multis (Basket Logic)
        // Find pairs of (product_id, transaction_id) for Multi transactions in range.
        $pairs = DB::table('vend_transaction_items as vti')
            ->join('vend_transactions as vt', 'vt.id', '=', 'vti.vend_transaction_id')
            ->whereBetween('vt.transaction_datetime', [$start, $end])
            ->where('vt.is_multiple', true)
            ->select('vti.product_id', 'vti.vend_transaction_id')
            ->distinct();

        // Join pairs with ALL items in those transactions and count them.
        $multiQuery = DB::table('vend_transaction_items as vti_all')
            ->joinSub($pairs, 'pairs', 'pairs.vend_transaction_id', '=', 'vti_all.vend_transaction_id')
            ->selectRaw('pairs.product_id, COUNT(*) as total_count')
            ->groupBy('pairs.product_id');

        // 3. Union
        $union = $singleQuery->unionAll($multiQuery);

        return DB::query()
            ->fromSub($union, 'product_sales')
            ->selectRaw('product_id, SUM(total_count) as total_count')
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

