<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProductMappingVendChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'order_qty',
        'order_qty_json',
        'delivery_product_mapping_id',
        'delivery_product_mapping_item_id',
        'delivery_product_mapping_vend_id',
        'is_active',
        'qty',
        // 'qty_sold_at',
        // 'qty_restocked_at',
        // 'qty_not_available_duration',
        'reserved_percent',
        'reserved_qty',
        'vend_channel_code',
        'vend_channel_id',
        'vend_code',
        'vend_id',
    ];

    protected $with = [
        'deliveryProductMappingVend.deliveryProductMapping.operator.country',
    ];

    protected $casts = [
        'order_qty_json' => 'json',
    ];

    // protected $casts = [
    //     'qty_sold_at' => 'datetime',
    //     'qty_restocked_at' => 'datetime',
    // ];

    // getter and setter
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value / $this->currencyDivisor(),
            set: fn (string $value) => $value * $this->currencyDivisor(),
        );
    }

    /**
     * Minor units per major unit for this row's operator country.
     *
     * Every link in the chain is optional at mutation time: on create() the
     * amount is filled before the foreign key is set, and the operator itself
     * can resolve to null. Falls back to 100 (exponent 2), matching the
     * previous behaviour when the mapping was missing.
     */
    protected function currencyDivisor(): int
    {
        $exponent = $this->deliveryProductMappingVend?->deliveryProductMapping?->operator?->country?->currency_exponent;

        return $exponent === null ? 100 : (int) pow(10, $exponent);
    }

    // relationships
    public function deliveryProductMappingItem()
    {
        return $this->belongsTo(DeliveryProductMappingItem::class);
    }

    public function deliveryProductMappingVend()
    {
        return $this->belongsTo(DeliveryProductMappingVend::class);
    }

    public function vendChannel()
    {
        return $this->belongsTo(VendChannel::class);
    }
}
