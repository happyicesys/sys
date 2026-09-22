<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMappingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_code',
        'product_id',
        'product_mapping_id',
        'selling_price_id',
        'sequence',
        'server_amount',
        'capacity_override',
    ];

    /**
     * The capacity this item gives its SKU on a SKU-stocked machine (Brian,
     * 2026-09-22): the mapping's "Reality" override when set, else the
     * product's default (freezer_slot_qty / chiller_slot_qty, Product → Edit).
     * 0 = unmeasured, which the dashboards read as "-". Meaningless for a
     * vending mapping — a vending slot's capacity is the board's.
     */
    public function effectiveCapacity(string $machineType): int
    {
        if ($this->capacity_override !== null) {
            return (int) $this->capacity_override;
        }
        $product = $this->relationLoaded('product') ? $this->product : $this->product()->first(['id', 'freezer_slot_qty', 'chiller_slot_qty']);

        return (int) match ($machineType) {
            Vend::MACHINE_TYPE_SMART_CHILLER => $product?->chiller_slot_qty,
            Vend::MACHINE_TYPE_SMART_FREEZER => $product?->freezer_slot_qty,
            default => 0,
        };
    }

    protected function serverAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    // relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productMapping()
    {
        return $this->belongsTo(ProductMapping::class);
    }

    public function sellingPrice()
    {
        return $this->belongsTo(SellingPrice::class);
    }
}
