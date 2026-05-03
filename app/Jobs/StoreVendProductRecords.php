<?php

namespace App\Jobs;

use App\Models\VendProductRecord;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * StoreVendProductRecords
 *
 * Aggregates vend transactions at the product level (one row per
 * vend_id × customer_id × product_id × date) and upserts the results
 * into vend_product_records.
 *
 * Design notes:
 * ─────────────
 * • Single transactions  (is_multiple = false / null):
 *     product_id resolved from vt.product_id → vc.product_id fallback
 *     success/fail determined by vend_channel_error vce.code
 *     amount / revenue / gross_profit taken directly from vend_transactions
 *
 * • Multiple transactions (is_multiple = true):
 *     product_id resolved from vti.product_id → vc.product_id fallback
 *     success/fail determined by vti.vend_channel_error_code (item level)
 *     amount taken from vti.unit_price_amount
 *     gross_profit = unit_price_amount - unit_cost  (both in cents)
 *
 * The two result sets are merged in PHP (keyed by vend/customer/product/date)
 * before upserting, so a product sold in both transaction modes on the same day
 * accumulates into one row.
 *
 * Product name, code, category and sub-category are denormalised into each row
 * so dashboard queries never need to join back to the products / categories
 * tables.
 */
class StoreVendProductRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $from;
    protected string $to;

    // Metric columns that should be summed when merging single + multi rows
    private const METRIC_COLS = [
        'total_amount',
        'total_count',
        'all_total_count',
        'error_count',
        'failure_count',
        'failure_amount',
        'revenue',
        'gross_profit',
        'online_success_amount',
        'online_success_count',
        'online_failure_amount',
        'online_failure_count',
    ];

    public function __construct(string $from, string $to)
    {
        $this->from = $from;
        $this->to   = $to;
    }

    public function handle(): void
    {
        $timezone = config('app.timezone');
        $dateFrom = Carbon::parse($this->from)->setTimezone($timezone)->startOfDay();
        $dateTo   = Carbon::parse($this->to)->setTimezone($timezone)->endOfDay();

        // ── 1. Aggregate single transactions ──────────────────────────────────
        $singleRows = DB::table('vend_transactions as vt')
            ->join('vends as v', 'vt.vend_id', '=', 'v.id')
            ->leftJoin('vend_channel_errors as vce', 'vt.vend_channel_error_id', '=', 'vce.id')
            ->leftJoin('vend_channels as vc', 'vt.vend_channel_id', '=', 'vc.id')
            ->leftJoin('delivery_platform_orders as dpo', 'vt.id', '=', 'dpo.vend_transaction_id')
            ->leftJoin('customers as c', 'vt.customer_id', '=', 'c.id')
            ->leftJoin('location_types as lt', 'c.location_type_id', '=', 'lt.id')
            ->where(function ($q) {
                $q->where('vt.is_multiple', false)->orWhereNull('vt.is_multiple');
            })
            ->where('vt.amount', '>', 0)
            ->whereBetween('vt.transaction_datetime', [$dateFrom, $dateTo])
            ->whereNotNull(DB::raw('COALESCE(vt.product_id, vc.product_id)'))
            ->select(
                DB::raw('COALESCE(vt.product_id, vc.product_id) as product_id'),
                'v.id as vend_id',
                'v.code as vend_code',
                'v.vend_prefix_id',
                'v.vend_model_id',
                'vt.customer_id',
                'vt.operator_id',
                'lt.id as location_type_id',
                DB::raw('DATE(vt.transaction_datetime) as date'),
                DB::raw('DAY(vt.transaction_datetime) as day'),
                DB::raw('MONTH(vt.transaction_datetime) as month'),
                DB::raw('MONTHNAME(vt.transaction_datetime) as monthname'),
                DB::raw('YEAR(vt.transaction_datetime) as year'),

                // Success: error is null OR code is 0/6
                DB::raw('SUM(CASE WHEN vt.vend_channel_error_id IS NULL OR vce.code IN (0,6)
                              THEN vt.amount ELSE 0 END) as total_amount'),
                DB::raw('SUM(CASE WHEN vt.vend_channel_error_id IS NULL OR vce.code IN (0,6)
                              THEN COALESCE(vt.qty,1) ELSE 0 END) as total_count'),
                DB::raw('SUM(COALESCE(vt.qty,1)) as all_total_count'),

                // Failure: error is set AND code is NOT 0/6
                DB::raw('SUM(CASE WHEN vt.vend_channel_error_id IS NOT NULL
                                   AND (vce.code IS NULL OR vce.code NOT IN (0,6))
                              THEN COALESCE(vt.qty,1) ELSE 0 END) as error_count'),
                DB::raw('SUM(CASE WHEN vt.vend_channel_error_id IS NOT NULL
                                   AND (vce.code IS NULL OR vce.code NOT IN (0,6))
                              THEN COALESCE(vt.qty,1) ELSE 0 END) as failure_count'),
                DB::raw('SUM(CASE WHEN vt.vend_channel_error_id IS NOT NULL
                                   AND (vce.code IS NULL OR vce.code NOT IN (0,6))
                              THEN vt.amount ELSE 0 END) as failure_amount'),

                // Revenue / GP (success only)
                DB::raw('SUM(CASE WHEN vt.vend_channel_error_id IS NULL OR vce.code IN (0,6)
                              THEN vt.revenue ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN vt.vend_channel_error_id IS NULL OR vce.code IN (0,6)
                              THEN vt.gross_profit ELSE 0 END) as gross_profit'),

                // Online channel (success)
                DB::raw('SUM(CASE WHEN dpo.id IS NOT NULL
                                   AND (vt.vend_channel_error_id IS NULL OR vce.code IN (0,6))
                              THEN vt.amount ELSE 0 END) as online_success_amount'),
                DB::raw('SUM(CASE WHEN dpo.id IS NOT NULL
                                   AND (vt.vend_channel_error_id IS NULL OR vce.code IN (0,6))
                              THEN COALESCE(vt.qty,1) ELSE 0 END) as online_success_count'),

                // Online channel (failure)
                DB::raw('SUM(CASE WHEN dpo.id IS NOT NULL
                                   AND vt.vend_channel_error_id IS NOT NULL
                                   AND (vce.code IS NULL OR vce.code NOT IN (0,6))
                              THEN vt.amount ELSE 0 END) as online_failure_amount'),
                DB::raw('SUM(CASE WHEN dpo.id IS NOT NULL
                                   AND vt.vend_channel_error_id IS NOT NULL
                                   AND (vce.code IS NULL OR vce.code NOT IN (0,6))
                              THEN COALESCE(vt.qty,1) ELSE 0 END) as online_failure_count'),
            )
            ->groupBy('date', 'v.id', 'vt.customer_id', DB::raw('COALESCE(vt.product_id, vc.product_id)'))
            ->get();

        // ── 2. Aggregate multiple transactions (item level) ───────────────────
        $multiRows = DB::table('vend_transactions as vt')
            ->join('vends as v', 'vt.vend_id', '=', 'v.id')
            ->join('vend_transaction_items as vti', 'vt.id', '=', 'vti.vend_transaction_id')
            ->leftJoin('vend_channels as vc', 'vti.vend_channel_id', '=', 'vc.id')
            ->leftJoin('delivery_platform_orders as dpo', 'vt.id', '=', 'dpo.vend_transaction_id')
            ->leftJoin('customers as c', 'vt.customer_id', '=', 'c.id')
            ->leftJoin('location_types as lt', 'c.location_type_id', '=', 'lt.id')
            ->where('vt.is_multiple', true)
            ->where('vt.amount', '>', 0)
            ->whereBetween('vt.transaction_datetime', [$dateFrom, $dateTo])
            ->whereNotNull(DB::raw('COALESCE(vti.product_id, vc.product_id)'))
            ->select(
                DB::raw('COALESCE(vti.product_id, vc.product_id) as product_id'),
                'v.id as vend_id',
                'v.code as vend_code',
                'v.vend_prefix_id',
                'v.vend_model_id',
                'vt.customer_id',
                'vt.operator_id',
                'lt.id as location_type_id',
                DB::raw('DATE(vt.transaction_datetime) as date'),
                DB::raw('DAY(vt.transaction_datetime) as day'),
                DB::raw('MONTH(vt.transaction_datetime) as month'),
                DB::raw('MONTHNAME(vt.transaction_datetime) as monthname'),
                DB::raw('YEAR(vt.transaction_datetime) as year'),

                // Item-level success: vti error code is 0/6 or null
                DB::raw('SUM(CASE WHEN vti.vend_channel_error_code IN (0,6) OR vti.vend_channel_error_code IS NULL
                              THEN COALESCE(vti.unit_price_amount,0) ELSE 0 END) as total_amount'),
                DB::raw('SUM(CASE WHEN vti.vend_channel_error_code IN (0,6) OR vti.vend_channel_error_code IS NULL
                              THEN 1 ELSE 0 END) as total_count'),
                DB::raw('COUNT(vti.id) as all_total_count'),

                // Item-level failure
                DB::raw('SUM(CASE WHEN NOT (vti.vend_channel_error_code IN (0,6) OR vti.vend_channel_error_code IS NULL)
                              THEN 1 ELSE 0 END) as error_count'),
                DB::raw('SUM(CASE WHEN NOT (vti.vend_channel_error_code IN (0,6) OR vti.vend_channel_error_code IS NULL)
                              THEN 1 ELSE 0 END) as failure_count'),
                DB::raw('SUM(CASE WHEN NOT (vti.vend_channel_error_code IN (0,6) OR vti.vend_channel_error_code IS NULL)
                              THEN COALESCE(vti.unit_price_amount,0) ELSE 0 END) as failure_amount'),

                // Revenue uses unit_price_amount; GP = price − cost (unit_cost stored in cents, same unit as unit_price_amount)
                DB::raw('SUM(CASE WHEN vti.vend_channel_error_code IN (0,6) OR vti.vend_channel_error_code IS NULL
                              THEN COALESCE(vti.unit_price_amount,0) ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN vti.vend_channel_error_code IN (0,6) OR vti.vend_channel_error_code IS NULL
                              THEN COALESCE(vti.unit_price_amount,0) - COALESCE(vti.unit_cost,0) ELSE 0 END) as gross_profit'),

                // Online channel (success)
                DB::raw('SUM(CASE WHEN dpo.id IS NOT NULL
                                   AND (vti.vend_channel_error_code IN (0,6) OR vti.vend_channel_error_code IS NULL)
                              THEN COALESCE(vti.unit_price_amount,0) ELSE 0 END) as online_success_amount'),
                DB::raw('SUM(CASE WHEN dpo.id IS NOT NULL
                                   AND (vti.vend_channel_error_code IN (0,6) OR vti.vend_channel_error_code IS NULL)
                              THEN 1 ELSE 0 END) as online_success_count'),

                // Online channel (failure)
                DB::raw('SUM(CASE WHEN dpo.id IS NOT NULL
                                   AND NOT (vti.vend_channel_error_code IN (0,6) OR vti.vend_channel_error_code IS NULL)
                              THEN COALESCE(vti.unit_price_amount,0) ELSE 0 END) as online_failure_amount'),
                DB::raw('SUM(CASE WHEN dpo.id IS NOT NULL
                                   AND NOT (vti.vend_channel_error_code IN (0,6) OR vti.vend_channel_error_code IS NULL)
                              THEN 1 ELSE 0 END) as online_failure_count'),
            )
            ->groupBy('date', 'v.id', 'vt.customer_id', DB::raw('COALESCE(vti.product_id, vc.product_id)'))
            ->get();

        // ── 3. Merge both result sets in PHP ──────────────────────────────────
        // Key: "{product_id}_{vend_id}_{customer_id}_{date}"
        $merged = [];

        foreach ($singleRows->merge($multiRows) as $row) {
            // Skip rows with no product resolved
            if (empty($row->product_id)) {
                continue;
            }

            $key = "{$row->product_id}_{$row->vend_id}_{$row->customer_id}_{$row->date}";

            if (!isset($merged[$key])) {
                $merged[$key] = (array) $row;
            } else {
                foreach (self::METRIC_COLS as $col) {
                    $merged[$key][$col] = ($merged[$key][$col] ?? 0) + ($row->{$col} ?? 0);
                }
            }
        }

        if (empty($merged)) {
            return;
        }

        // ── 4. Bulk-fetch product metadata (name, code, categories) ───────────
        $productIds = array_unique(array_column($merged, 'product_id'));

        $products = DB::table('products as p')
            ->leftJoin('categories as cat', 'p.category_id', '=', 'cat.id')
            ->leftJoin('category_groups as cg', 'p.category_group_id', '=', 'cg.id')
            ->whereIn('p.id', $productIds)
            ->select(
                'p.id as product_id',
                'p.code as product_code',
                'p.name as product_name',
                'p.category_id',
                'cat.name as category_name',
                'p.category_group_id',
                'cg.name as category_group_name',
                // product_sub_category_id may not be present in all environments;
                // use DB::raw to avoid column-not-found errors on older schemas.
                DB::raw('NULL as product_sub_category_id'),
                DB::raw('NULL as product_sub_category_name'),
            )
            ->get()
            ->keyBy('product_id');

        // If the column exists (added via migration), override the NULLs above.
        if ($this->productSubCategoryColumnExists()) {
            $withSubCat = DB::table('products as p')
                ->leftJoin('product_sub_categories as psc', 'p.product_sub_category_id', '=', 'psc.id')
                ->whereIn('p.id', $productIds)
                ->select(
                    'p.id as product_id',
                    'p.product_sub_category_id',
                    'psc.name as product_sub_category_name',
                )
                ->get()
                ->keyBy('product_id');

            foreach ($withSubCat as $pid => $row) {
                if (isset($products[$pid])) {
                    $products[$pid]->product_sub_category_id   = $row->product_sub_category_id;
                    $products[$pid]->product_sub_category_name = $row->product_sub_category_name;
                }
            }
        }

        // ── 5. Upsert into vend_product_records ───────────────────────────────
        foreach ($merged as $row) {
            $productId = $row['product_id'];
            $product   = $products->get($productId);

            VendProductRecord::updateOrCreate(
                [
                    'vend_id'     => $row['vend_id'],
                    'customer_id' => $row['customer_id'],
                    'product_id'  => $productId,
                    'date'        => $row['date'],
                ],
                array_merge($row, [
                    // Denormalised product fields
                    'product_code'              => $product?->product_code,
                    'product_name'              => $product?->product_name,
                    'category_id'               => $product?->category_id,
                    'category_name'             => $product?->category_name,
                    'category_group_id'         => $product?->category_group_id,
                    'category_group_name'       => $product?->category_group_name,
                    'product_sub_category_id'   => $product?->product_sub_category_id,
                    'product_sub_category_name' => $product?->product_sub_category_name,
                ])
            );
        }
    }

    /**
     * Detect whether the products table has a product_sub_category_id column.
     * Cached after the first check so we only hit information_schema once per job run.
     */
    private ?bool $subCatColExists = null;

    private function productSubCategoryColumnExists(): bool
    {
        if ($this->subCatColExists !== null) {
            return $this->subCatColExists;
        }

        $this->subCatColExists = DB::getSchemaBuilder()->hasColumn('products', 'product_sub_category_id');

        return $this->subCatColExists;
    }
}
