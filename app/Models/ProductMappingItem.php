<?php

namespace App\Models;

use App\Support\ChannelCode;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMappingItem extends Model
{
    use HasFactory;

    /** Freezer baskets that hold half a SKU's listed capacity (Brian, 2026-11-04). */
    public const FREEZER_HALF_BASKETS = [1, 4];

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
     * 2026-09-22): the mapping's "Real Capacity" override (capacity_override) when set, else the
     * product's default for this slot (see productDefaultCapacity).
     * 0 = unmeasured, which the dashboards read as "-". Meaningless for a
     * vending mapping — a vending slot's capacity is the board's.
     */
    public function effectiveCapacity(string $machineType): int
    {
        if ($this->capacity_override !== null) {
            return (int) $this->capacity_override;
        }

        return self::productDefaultCapacity(
            $this->relationLoaded('product') ? $this->product : $this->product()->first(['id', 'freezer_slot_qty', 'chiller_slot_qty']),
            $machineType,
            $this->channel_code
        );
    }

    /**
     * The SKU's own default capacity for this kind of machine — the "Default"
     * column on ProductMapping → Edit, set on Product → Edit. 0 = unmeasured,
     * which the dashboards read as "-". One definition, shared by the effective
     * capacity above and by the controller's override normalisation.
     *
     * In freezer baskets 1 and 4 the default is half that number, rounded down to
     * whole pieces, and never below 1 for a measured product.
     */
    public static function productDefaultCapacity(?Product $product, string $machineType, string|int|null $channelCode = null): int
    {
        $capacity = (int) match ($machineType) {
            Vend::MACHINE_TYPE_SMART_CHILLER => $product?->chiller_slot_qty,
            Vend::MACHINE_TYPE_SMART_FREEZER => $product?->freezer_slot_qty,
            default => 0,
        };

        if ($capacity > 0 && $machineType === Vend::MACHINE_TYPE_SMART_FREEZER && self::isFreezerHalfBasket($channelCode)) {
            return max(1, intdiv($capacity, 2));
        }

        return $capacity;
    }

    /** A freezer code is <basket><division>, so the basket is its first digit. */
    private static function isFreezerHalfBasket(string|int|null $channelCode): bool
    {
        $parsed = ChannelCode::parse($channelCode);

        return $parsed !== null && in_array(intdiv($parsed['code'], 10), self::FREEZER_HALF_BASKETS, true);
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
