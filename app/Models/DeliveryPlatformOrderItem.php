<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatformOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'delivery_platform_order_id',
        'is_cancelled',
        'is_edited',
        'product_id',
        'product_json',
        'delivery_product_mapping_item_id',
        'qty'
    ];

    protected $casts = [
        'product_json' => 'json'
    ];

    protected $with = ['deliveryProductMappingItem.deliveryProductMapping.operator.country'];

    // getter and setter
    protected function amount(): Attribute
    {
        // return Attribute::make(
        //     get: fn (string $value) => $value/ ($this->deliveryProductMappingItem and $this->deliveryProductMappingItem->deliveryProductMapping and $this->deliveryProductMappingItem->deliveryProductMapping->operator ? pow(10, $this->deliveryProductMappingItem->deliveryProductMapping->operator->country->currency_exponent) : 100),
        // );
        return Attribute::make(
            get: fn (string $value) => $value/ ($this->deliveryProductMappingItem->deliveryProductMapping ? pow(10, $this->deliveryProductMappingItem->deliveryProductMapping->operator->country->currency_exponent) : 100) ,
        );
    }

    // relationships
    public function deliveryPlatformOrder()
    {
        return $this->belongsTo(DeliveryPlatformOrder::class);
    }

    public function deliveryProductMappingItem()
    {
        return $this->belongsTo(DeliveryProductMappingItem::class);
    }

    public function orderItemVendChannels()
    {
        return $this->hasMany(OrderItemVendChannel::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
