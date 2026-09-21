<?php

namespace App\Services\Citybox\DTO;

use App\Services\Citybox\ChillerPlanogram;

/**
 * One channel of a chiller's planogram, as mark1 defines it (2026-09-21).
 *
 * The layout is ours: code and product come from the vend's ProductMapping,
 * capacity from the SKU (products.chiller_slot_qty). CityBox only ever tells
 * us a quantity and a price, and only per PRODUCT — so a SKU that sits on two
 * codes (two facings) is split across them by ChillerChannelMap::allocate.
 */
final readonly class ChillerSlot
{
    public function __construct(
        public int $code,
        public int $cityboxProductId,
        public int $productId,
        public int $capacity,
    ) {}

    public function layer(): int
    {
        return ChillerPlanogram::layerOf($this->code);
    }
}
