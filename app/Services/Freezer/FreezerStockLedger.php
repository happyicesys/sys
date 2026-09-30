<?php

namespace App\Services\Freezer;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\OpsJobItem;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use App\Support\DispenseVerdict;
use Illuminate\Support\Facades\DB;

/**
 * A Smart Freezer's stock, per SKU: in on Stock In, out on each sale.
 *
 * A vending machine's `vend_channels.qty` is whatever its VMC last reported, and a CityBox chiller's
 * is CityBox's `device_product` qty. A freezer reports neither, so mark1 keeps the count itself
 * (FreezerChannelSync already carries it across mapping changes, but until 2026-09-30 nothing wrote
 * it: 50001 stocked 3 cheesecakes in job 58533 and still read 0).
 *
 *  - **Stock In** adds each SKU's refill (`actual_qty`, negative for a return). Relative, not
 *    "count + refill": the driver never types a count — the page sends back the qty it loaded, so
 *    an absolute write would put back every sale made while the page was open.
 *  - **Undo Stock In** takes the refill back out — the exact inverse. Only on rows still in the
 *    planogram: after a mapping swap a retired SKU's returned stock must not come back.
 *  - **A sale** takes one off per unit the TRADE reports dispensed, by product (`transf_info[].goods_id`
 *    = mark1 product id), once — only when the ingest creates or first fills the row.
 *
 * Never below zero: a sale the ledger never saw stocked in (the freezer went live before this
 * existed) must not leave a negative count on the dashboard. The AI verdict does not move stock yet
 * (Brian decides what a took_more / took_less should do).
 */
class FreezerStockLedger
{
    public function stockIn(OpsJobItem $item): void
    {
        $vend = $item->vend;
        if (! $vend || ! $vend->isSmartFreezer()) {
            return;
        }

        foreach ($item->opsJobItemChannels()->get() as $line) {
            $refill = (int) $line->actual_qty;
            if (! $line->vend_channel_id || $refill === 0) {
                continue;
            }
            VendChannel::whereKey($line->vend_channel_id)->where('vend_id', $vend->id)
                ->update(['qty' => DB::raw('GREATEST(CAST(qty AS SIGNED) + '.$refill.', 0)')]);
        }

        $this->refreshJson($vend);
    }

    public function undoStockIn(OpsJobItem $item): void
    {
        $vend = $item->vend;
        if (! $vend || ! $vend->isSmartFreezer()) {
            return;
        }

        foreach ($item->opsJobItemChannels()->get() as $line) {
            $refill = (int) $line->actual_qty;
            if (! $line->vend_channel_id || $refill === 0) {
                continue;
            }
            VendChannel::whereKey($line->vend_channel_id)->where('vend_id', $vend->id)->where('is_active', true)
                ->update(['qty' => DB::raw('GREATEST(CAST(qty AS SIGNED) - '.$refill.', 0)')]);
        }

        $this->refreshJson($vend);
    }

    /**
     * Called inside the ingest transaction, only on the call that creates or first fills the row,
     * so a replayed TRADE never deducts twice.
     */
    public function sale(Vend $vend, VendTransaction $sale): void
    {
        if (! $vend->isSmartFreezer()) {
            return;
        }

        foreach ($this->unitsDispensed($sale) as $productId => $units) {
            VendChannel::where('vend_id', $vend->id)->where('product_id', $productId)
                ->update(['qty' => DB::raw('GREATEST(CAST(qty AS SIGNED) - '.(int) $units.', 0)')]);
        }

        // After commit: the dashboard JSON must read the committed qty.
        DB::afterCommit(fn () => $this->refreshJson($vend));
    }

    /** @return array<int, int> product id => units dispensed */
    public function unitsDispensed(VendTransaction $sale): array
    {
        $frame = $sale->vend_transaction_json;
        if (is_string($frame)) {
            $frame = json_decode($frame, true);
        }

        $units = [];
        foreach ((array) (($frame['transf_info'] ?? null) ?: []) as $unit) {
            $productId = (int) ($unit['goods_id'] ?? 0);
            $code = isset($unit['SErr']) ? (int) $unit['SErr'] : null;
            if ($productId > 0 && DispenseVerdict::isDispensed($code)) {
                $units[$productId] = ($units[$productId] ?? 0) + 1;
            }
        }

        return $units;
    }

    private function refreshJson(Vend $vend): void
    {
        SaveVendChannelsJson::dispatch($vend->id)->onQueue('default');
    }
}
