<?php

namespace App\Services\Citybox\DTO;

use App\Services\Citybox\ChillerPlanogram;
use App\Support\ChannelCode;

/**
 * One SKU of a chiller's planogram, as mark1 defines it (2026-09-22: keyed by
 * SKU, no longer by slot). The layout is ours: position and product come from
 * the vend's ProductMapping, capacity from the mapping item's override or the
 * SKU's chiller_slot_qty. CityBox only ever tells us a quantity and a price,
 * per PRODUCT — which is exactly the unit this is.
 *
 * `code` + `suffix` is the primary position label ("101", "101A"); `labels`
 * lists every code the mapping puts the SKU on (normally one).
 */
final readonly class ChillerSlot
{
    /** @param  list<string>  $labels */
    public function __construct(
        public int $code,
        public ?string $suffix,
        public int $cityboxProductId,
        public int $productId,
        public int $capacity,
        public array $labels = [],
    ) {}

    public function layer(): int
    {
        return ChillerPlanogram::layerOf($this->code);
    }

    public function label(): string
    {
        return ChannelCode::label($this->code, $this->suffix);
    }
}
