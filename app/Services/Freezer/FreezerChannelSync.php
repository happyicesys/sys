<?php

namespace App\Services\Freezer;

use App\Jobs\Vend\SyncVendChannels;
use App\Models\SellingPrice;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\Stock\SkuPlanogram;

/**
 * Writes a Smart Freezer's `vend_channels` from its planogram, one row per SKU.
 *
 * A vending machine's channels arrive from the board's own CHANNEL frame; a freezer never sends one
 * (its APK treats CHANNEL purely as a "re-pull your menu" nudge), so the bound ProductMapping is the
 * source. Since 2026-09-22 the row is the SKU (Brian): a product that moves from basket 11 to 21
 * between two mappings is the same stock, so its qty rides along and only the position label
 * changes. Before that the row was the code, and a basket move retired the old row with its qty.
 *
 * Two things this deliberately does NOT own:
 *  - **qty.** It is our ledger (ops-job topup in, each sale out), not a supplier feed, so an existing
 *    row keeps its quantity through every re-sync. A new SKU starts empty.
 *  - **capacity.** The mapping item's override, else the SKU's measured par (`products.freezer_slot_qty`).
 *    Unmeasured products leave it at 0, which reads as "-" on the dashboard rather than as a made-up
 *    par; a freezer row stays active either way.
 *
 * Prices are the Site's RP tier, which is what a freezer sells at — it has no board price
 * (Vend::serverPriceType). A SKU that has left the planogram is retired by SyncVendChannels itself
 * (a SKU-stocked machine's rows missing from the frame go inactive), never left as a sold-out ghost.
 */
class FreezerChannelSync
{
    public function __construct(private SkuPlanogram $planogram) {}

    /**
     * @return int the number of SKUs pushed, 0 when this vend is not a freezer with a mapping
     */
    public function sync(Vend $vend): int
    {
        if (! $vend->isSmartFreezer()) {
            return 0;
        }

        $skus = $this->planogram->forVend($vend);
        if ($skus === []) {
            return 0;
        }

        $existing = VendChannel::where('vend_id', $vend->id)->whereNotNull('product_id')->get()->keyBy(fn ($row) => (int) $row->product_id);
        $productIds = array_keys($skus);
        $prices = $this->serverPrices($vend, $productIds);
        $fallback = \App\Models\ProductMappingItem::withoutGlobalScopes()
            ->where('product_mapping_id', $vend->product_mapping_id)
            ->whereIn('product_id', $productIds)
            ->whereNotNull('server_amount')
            ->get(['product_id', 'server_amount'])
            ->mapWithKeys(fn ($i) => [(int) $i->product_id => (int) $i->getRawOriginal('server_amount')])
            ->all();

        $channels = [];
        foreach ($skus as $productId => $sku) {
            $row = $existing->get($productId);
            $channels[] = [
                'channel_code' => $sku->code,
                'suffix' => $sku->suffix,
                'product_id' => $productId,
                'qty' => (int) ($row?->qty ?? 0),
                'capacity' => $sku->capacity,
                'amount' => (int) ($prices[$productId] ?? $fallback[$productId] ?? 0),
                'amount2' => 0,
                'error_code' => 0,
            ];
        }

        // Same path a machine's own frame takes, so the rows, the vend_channels_json the dashboard
        // reads, the delivery-platform mirror and the error-rate columns all stay in step.
        SyncVendChannels::dispatch(['channels' => $channels], $vend)->onQueue('high');

        return count($channels);
    }

    /**
     * Site-tier selling price per product, in cents. Empty when the machine has no Site or its
     * pricing source is off — then the planogram item's own server_amount is the fallback.
     *
     * @param  array<int,int>  $productIds
     * @return array<int,int>
     */
    private function serverPrices(Vend $vend, array $productIds): array
    {
        $type = $vend->serverPriceType();
        if (! $type || $productIds === []) {
            return [];
        }

        return SellingPrice::whereIn('product_id', $productIds)
            ->where('type', $type)
            ->get()
            ->mapWithKeys(fn ($price) => [$price->product_id => (int) $price->getRawOriginal('amount')])
            ->all();
    }
}
