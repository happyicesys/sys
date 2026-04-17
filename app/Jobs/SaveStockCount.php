<?php

namespace App\Jobs;

use App\Models\StockCount;
use App\Models\Vend;
use App\Services\CmsService;
use Carbon\Carbon;
use DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SaveStockCount implements ShouldQueue
{
    use Queueable;

    /** @var CmsService */
    protected $cmsService;

    protected $date;
    protected $vendID;

    public function __construct($date, $vendID)
    {
        // Correct spelling and assign once
        $this->cmsService = new CmsService();
        $this->date = $date;
        $this->vendID = $vendID;
    }

    public function handle(): void
    {
        $vend = Vend::with(['customer', 'productMapping'])->find($this->vendID);
        if (!$vend || !$vend->customer || !$vend->productMapping) return;

        $dateObj = Carbon::parse($this->date);

        // Sales (assumed in cents)
        // Use a range instead of whereDate() — DATE() wraps prevent index usage on timestamp columns
        $vendTransactions = $vend->vendTransactions()
            ->whereBetween('created_at', [
                $dateObj->copy()->startOfDay(),
                $dateObj->copy()->endOfDay(),
            ])
            ->get();
        $cashSalesCents    = (int) $vendTransactions->where('payment_method_id', 1)->sum('amount');
        $cashlessSalesCents= (int) $vendTransactions->where('payment_method_id', '!=', 1)->sum('amount');

        // Coin float (decide if this is cents or a coin count — here we assume *cents*)
        $coinFloatCents = 0;
        if (!empty($vend->parameter_json)) {
            $param = is_array($vend->parameter_json) ? $vend->parameter_json : json_decode($vend->parameter_json, true);
            if (isset($param['CoinCnt'])) {
                $coinFloatCents = (int) $param['CoinCnt']; // if this is a COUNT, convert to RM before set or store to another column
            }
        }

        // Create the StockCount parent (mutators expect RM on set → divide by 100)
        $stockCount = StockCount::updateOrCreate(
            [
                'vend_id' => $this->vendID,
                'day'     => $dateObj->day,
                'month'   => $dateObj->month,
                'year'    => $dateObj->year,
            ],
            [
                'cash_sales_amount'     => $cashSalesCents,
                'cashless_sales_amount' => $cashlessSalesCents,
                'coin_float_amount'     => $coinFloatCents,
                'customer_id'           => $vend->customer_id ?? null,
                'location_type_id'      => $vend->location_type_id ?? null,
                'operator_id'           => $vend->operator_id ?? null,
                'product_mapping_id'    => $vend->product_mapping_id ?? null,
                'vend_code'             => $vend->vend_code ?? null,
                'vend_contract_id'      => $vend->vend_contract_id ?? null,
                'vend_model_id'         => $vend->vend_model_id ?? null,
                'vend_prefix_id'        => $vend->vend_prefix_id ?? null,
            ]
        );

        if (!$stockCount) return;

        // Pull CMS available qty once (code => qty)
        $cmsList = $this->cmsService->getCMSQtyAvailableApi() ?: [];
        $cmsQtyByCode = [];
        foreach ($cmsList as $row) {
            if (isset($row['code'])) $cmsQtyByCode[$row['code']] = (int) ($row['qty'] ?? 0);
        }

        // Build per-product aggregates + not-yet-synced picked qty
        $perProducts = DB::table('vend_channels as vc')
            ->join('products as p', 'p.id', '=', 'vc.product_id')
            ->leftJoin(DB::raw('
                (
                    SELECT
                        ojic.product_id,
                        SUM(ojic.picked_qty) AS not_yet_sync_qty
                    FROM ops_job_item_channels ojic
                    JOIN ops_job_items oji ON oji.id = ojic.ops_job_item_id
                    JOIN ops_jobs oj ON oj.id = oji.ops_job_id
                    WHERE DATE(oj.date) >= CURRENT_DATE
                    AND oji.cms_transaction_id IS NULL
                    GROUP BY ojic.product_id
                ) ny
            '), 'ny.product_id', '=', 'vc.product_id')
            ->where('vc.vend_id', $this->vendID)
            ->where('vc.is_active', true)
            ->where('vc.capacity', '>', 0)
            ->groupBy('vc.product_id', 'p.code')
            ->select('vc.product_id', 'p.code')
            // machine totals per product (no warehouse here)
            ->selectRaw('COALESCE(SUM(vc.qty), 0)             AS qty_vend')
            ->selectRaw('COALESCE(SUM(vc.amount * vc.qty), 0) AS value_cents')
            // latest unit cost per product (one value, not per-channel)
            ->selectRaw('(
                SELECT uc2.cost
                FROM unit_costs uc2
                WHERE uc2.product_id = vc.product_id
                ORDER BY uc2.is_current DESC, uc2.date_from DESC, uc2.created_at DESC
                LIMIT 1
            ) AS unit_cost_cents')
            // ->selectRaw('COALESCE(ny.not_yet_sync_qty, 0)     AS not_yet_sync_qty')
            ->get();

        // Upsert items; pass cents to money fields (mutators handle cents)
        $seen = [];
        foreach ($perProducts as $row) {
            $seen[] = (int) $row->product_id;

            // warehouse qty once per product
            $cmsAvailable = $cmsQtyByCode[$row->code] ?? 0;
            // $qtyWarehouse = max(0, (int) $cmsAvailable - (int) $row->not_yet_sync_qty);
            $qtyWarehouse = max(0, (int) $cmsAvailable);

            // cost = latest_unit_cost × (sum channel qty + warehouse qty)
            $unitCostCents = (int) ($row->unit_cost_cents ?? 0);
            $stockCost     = $unitCostCents * (int) $row->qty_vend;

            $stockCount->stockCountItems()->updateOrCreate(
                ['product_id' => (int) $row->product_id],
                [
                    'product_id'         => (int) $row->product_id,
                    'qty_vend'           => (int) $row->qty_vend,
                    'qty_warehouse'      => (int) $qtyWarehouse,
                    'stock_value_amount' => (int) $row->value_cents,  // machine value only
                    'stock_cost_amount'  => (int) $stockCost,         // total cost (machine+WH)
                    'unit_cost_amount' => (int) $unitCostCents, // latest unit cost
                ]
            );
        }

        // Optional cleanup unchanged…
        if (!empty($seen)) {
            $stockCount->stockCountItems()->whereNotIn('product_id', $seen)->delete();
        }
    }
}
