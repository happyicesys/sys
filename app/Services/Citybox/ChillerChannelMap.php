<?php

namespace App\Services\Citybox;

use App\Models\CityboxProduct;
use App\Models\Vend;
use App\Services\Citybox\DTO\ChillerSlot;
use App\Services\Stock\SkuPlanogram;

/**
 * A chiller's SKUs, read from ITS OWN mapping (Brian, 2026-09-21), keyed by
 * PRODUCT (Brian, 2026-09-22).
 *
 * Their side is per product and so is ours now: one vend_channels row per SKU,
 * whose code (+ suffix) is only where the driver puts it. So there is no
 * spreading of one quantity over "facings" on the way in and no summing on
 * the way out any more — the live qty of a SKU is the row's qty, and the
 * driver's count for a SKU is one number to CityBox.
 */
class ChillerChannelMap
{
    public function __construct(private SkuPlanogram $planogram) {}

    /**
     * The vend's SKUs, ordered by position. Empty when nothing is mapped yet —
     * callers must read that as "no planogram", never as "no stock".
     *
     * @return array<int,ChillerSlot> keyed by mark1 product id
     */
    public function forVend(Vend $vend): array
    {
        return $this->forMapping($vend->product_mapping_id);
    }

    /**
     * The slots a mapping WOULD give a chiller — the vend's current one, or an
     * upcoming one being checked before a changeover.
     *
     * @return array<int,ChillerSlot> keyed by mark1 product id
     */
    public function forMapping(?int $productMappingId): array
    {
        $skus = $this->planogram->forMapping($productMappingId, Vend::MACHINE_TYPE_SMART_CHILLER);
        if ($skus === []) {
            return [];
        }

        $cityboxIds = $this->cityboxIdsFor(array_keys($skus));

        $slots = [];
        foreach ($skus as $productId => $sku) {
            $cityboxId = $cityboxIds[$productId] ?? null;
            if ($cityboxId === null) {
                // A product nobody has linked to a CityBox SKU: no channel, rather
                // than a channel we could never push or fill.
                continue;
            }
            $slots[$productId] = new ChillerSlot(
                code: $sku->code,
                suffix: $sku->suffix,
                cityboxProductId: $cityboxId,
                productId: $productId,
                capacity: $sku->capacity,
                labels: $sku->labels,
            );
        }

        return $slots;
    }

    /** @param  array<int,ChillerSlot>  $slots  @return ChillerSlot|null the slot carrying that CityBox SKU */
    public static function byCityboxId(array $slots, int $cityboxProductId): ?ChillerSlot
    {
        foreach ($slots as $slot) {
            if ($slot->cityboxProductId === $cityboxProductId) {
                return $slot;
            }
        }

        return null;
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
}
