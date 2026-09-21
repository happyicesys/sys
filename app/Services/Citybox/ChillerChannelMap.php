<?php

namespace App\Services\Citybox;

use App\Models\CityboxProduct;
use App\Models\ProductMappingItem;
use App\Models\Vend;
use App\Services\Citybox\DTO\ChillerSlot;

/**
 * A chiller's channels, read from ITS OWN mapping (Brian, 2026-09-21).
 *
 * Until now the channels were a mirror of CityBox's Pre-Stock Setup, so a
 * template switch in their portal wiped a machine's layout here (prod
 * 2026-09-19, C5001). mark1 owns the layout now: the vend's ProductMapping
 * decides which codes exist and what sits on them, the SKU decides capacity
 * (products.chiller_slot_qty), and CityBox supplies only quantity and price.
 *
 * Their side is per PRODUCT, ours is per CHANNEL, so the two shapes meet here:
 *  - reading  (allocate)  one live quantity → the codes carrying that SKU
 *  - writing  (sumBySku)  the codes' counts → one number per SKU for their API
 */
class ChillerChannelMap
{
    /**
     * The vend's channels, ordered by code. Empty when nothing is mapped yet —
     * callers must read that as "no planogram", never as "no stock".
     *
     * @return array<int,ChillerSlot> keyed by channel code
     */
    public function forVend(Vend $vend): array
    {
        return $this->forMapping($vend->product_mapping_id);
    }

    /**
     * The slots a mapping WOULD give a chiller — the vend's current one, or an
     * upcoming one being checked before a changeover.
     *
     * @return array<int,ChillerSlot> keyed by channel code
     */
    public function forMapping(?int $productMappingId): array
    {
        if (! $productMappingId) {
            return [];
        }

        $items = ProductMappingItem::withoutGlobalScopes()
            ->where('product_mapping_id', $productMappingId)
            ->whereNotNull('product_id')
            ->with('product:id,chiller_slot_qty')
            ->get(['id', 'product_mapping_id', 'channel_code', 'product_id']);

        if ($items->isEmpty()) {
            return [];
        }

        $cityboxIds = $this->cityboxIdsFor($items->pluck('product_id')->unique()->all());

        $slots = [];
        foreach ($items as $item) {
            $code = (int) $item->channel_code;
            $cityboxId = $cityboxIds[(int) $item->product_id] ?? null;
            if ($cityboxId === null || ! ChillerPlanogram::isChillerCode($code)) {
                // A product nobody has linked to a CityBox SKU, or a code from
                // before the 101–599 rule: no channel, rather than a channel we
                // could never push or fill.
                continue;
            }
            $slots[$code] = new ChillerSlot(
                code: $code,
                cityboxProductId: $cityboxId,
                productId: (int) $item->product_id,
                capacity: (int) ($item->product?->chiller_slot_qty ?? 0),
            );
        }
        ksort($slots);

        return $slots;
    }

    /**
     * mark1 product → CityBox SKU id. Their catalogue carries duplicate SKUs for
     * the same item, so a product can link to several; the lowest id wins, which
     * is stable and matches what the catalog sync created the product from.
     *
     * @param  array<int,int>  $productIds
     * @return array<int,int>
     */
    private function cityboxIdsFor(array $productIds): array
    {
        return CityboxProduct::whereIn('product_id', $productIds)
            ->where('is_delisted', false)
            ->orderBy('citybox_product_id')
            ->pluck('citybox_product_id', 'product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Spread each SKU's live quantity over the codes that carry it: fill the
     * lowest code to capacity, then the next. One facing gets everything, which
     * is the common case; two facings of 5 with 7 in the cabinet read 5 and 2.
     * Capacity 0 (nobody has measured the SKU) puts it all on the first code
     * rather than losing it.
     *
     * @param  array<int,ChillerSlot>  $slots
     * @param  array<int,int>  $qtyByCityboxId
     * @return array<int,int> channel code => qty
     */
    public static function allocate(array $slots, array $qtyByCityboxId): array
    {
        $bySku = [];
        foreach ($slots as $slot) {
            $bySku[$slot->cityboxProductId][] = $slot;
        }

        $out = [];
        foreach ($bySku as $cityboxId => $skuSlots) {
            $left = max(0, (int) ($qtyByCityboxId[$cityboxId] ?? 0));
            $last = count($skuSlots) - 1;
            foreach ($skuSlots as $i => $slot) {
                $take = ($i === $last || $slot->capacity <= 0) ? $left : min($left, $slot->capacity);
                $out[$slot->code] = $take;
                $left -= $take;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * The other direction, for `device_stock_submit`: their API takes one count
     * per product, so the counts of every code carrying a SKU are added up.
     *
     * @param  array<int,ChillerSlot>  $slots
     * @param  array<int,int>  $qtyByCode
     * @return array<int,int> citybox product id => qty
     */
    public static function sumBySku(array $slots, array $qtyByCode): array
    {
        $out = [];
        foreach ($qtyByCode as $code => $qty) {
            $slot = $slots[(int) $code] ?? null;
            if (! $slot) {
                continue;
            }
            $out[$slot->cityboxProductId] = ($out[$slot->cityboxProductId] ?? 0) + max(0, (int) $qty);
        }

        return $out;
    }
}
