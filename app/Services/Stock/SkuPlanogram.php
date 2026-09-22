<?php

namespace App\Services\Stock;

use App\Models\ProductMappingItem;
use App\Models\Vend;
use App\Services\Citybox\ChillerPlanogram;
use App\Services\Stock\DTO\SkuSlot;
use App\Support\ChannelCode;

/**
 * A SKU-stocked machine's planogram read the way its stock is kept: one entry
 * per PRODUCT (Brian, 2026-09-22). Both the Smart Freezer and the Smart
 * Chiller keep qty and capacity per SKU — CityBox's API has no channel at all,
 * and a freezer basket is a placement hint — so a SKU that moves from 101 to
 * 501 between two mappings is the same stock, not a return plus a fresh pick.
 *
 * Reads product_mapping_items unscoped: the operator global scope must not
 * turn "another operator's mapping" into "no planogram".
 */
class SkuPlanogram
{
    /** @return array<int,SkuSlot> keyed by product id, ordered by primary label */
    public function forVend(Vend $vend): array
    {
        return $this->forMapping($vend->product_mapping_id, (string) $vend->machine_type);
    }

    /** @return array<int,SkuSlot> keyed by product id, ordered by primary label */
    public function forMapping(?int $productMappingId, string $machineType): array
    {
        if (! $productMappingId) {
            return [];
        }

        $items = ProductMappingItem::withoutGlobalScopes()
            ->where('product_mapping_id', $productMappingId)
            ->whereNotNull('product_id')
            ->with('product:id,freezer_slot_qty,chiller_slot_qty')
            ->get(['id', 'product_mapping_id', 'channel_code', 'product_id', 'capacity_override']);

        /** @var array<int,array{codes:list<array{code:int,suffix:?string}>,capacity:int}> $bySku */
        $bySku = [];
        foreach ($items as $item) {
            $parsed = ChannelCode::parse($item->channel_code);
            if (! $parsed || ! $this->codeIsValidFor($machineType, $parsed['code'])) {
                // A code outside the machine's scheme (legacy junk) gives the SKU no
                // position — but the SKU still exists if another item carries it.
                continue;
            }
            $pid = (int) $item->product_id;
            $bySku[$pid]['codes'][] = $parsed;
            $bySku[$pid]['capacity'] = ($bySku[$pid]['capacity'] ?? 0) + $item->effectiveCapacity($machineType);
        }

        $slots = [];
        foreach ($bySku as $pid => $sku) {
            usort($sku['codes'], fn ($a, $b) => [$a['code'], $a['suffix'] ?? ''] <=> [$b['code'], $b['suffix'] ?? '']);
            $primary = $sku['codes'][0];
            $slots[$pid] = new SkuSlot(
                productId: $pid,
                code: $primary['code'],
                suffix: $primary['suffix'],
                labels: array_map(fn ($c) => ChannelCode::label($c['code'], $c['suffix']), $sku['codes']),
                capacity: $sku['capacity'],
            );
        }
        uasort($slots, fn (SkuSlot $a, SkuSlot $b) => [$a->code, $a->suffix ?? ''] <=> [$b->code, $b->suffix ?? '']);

        return $slots;
    }

    /**
     * The position scheme per machine kind. Chiller: <layer><position 2 digits>,
     * 101–599. Freezer: <basket><division>, 11–69 (same digits a vending board
     * uses, so SyncVendChannels' range check is shared).
     */
    public function codeIsValidFor(string $machineType, int $code): bool
    {
        return match ($machineType) {
            Vend::MACHINE_TYPE_SMART_CHILLER => ChillerPlanogram::isChillerCode($code),
            Vend::MACHINE_TYPE_SMART_FREEZER => $code >= 10 && $code <= 69,
            default => $code > 0,
        };
    }
}
