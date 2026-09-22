<?php

namespace App\Services\Stock\DTO;

use App\Support\ChannelCode;

/**
 * One SKU of a SKU-stocked machine's planogram (Smart Freezer, Smart Chiller):
 * the unit of stock identity since 2026-09-22. `code` + `suffix` is its
 * primary position label (the lowest one when the mapping lists the SKU on
 * several codes — "never happens", but nothing forbids it); `labels` are all
 * of them; `capacity` is the sum of the items' effective capacity
 * (product_mapping_items.capacity_override ?? the product's par).
 */
final readonly class SkuSlot
{
    /** @param  list<string>  $labels */
    public function __construct(
        public int $productId,
        public int $code,
        public ?string $suffix,
        public array $labels,
        public int $capacity,
    ) {}

    public function label(): string
    {
        return ChannelCode::label($this->code, $this->suffix);
    }
}
