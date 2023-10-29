<?php

namespace App\Models;

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
        'product_mapping_item_id',
        'qty'
    ];

    // relationships
    public function deliveryPlatformOrder()
    {
        return $this->belongsTo(DeliveryPlatformOrder::class);
    }

    public function orderItemVendChannels()
    {
        return $this->hasMany(OrderItemVendChannel::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productMappingItem()
    {
        return $this->belongsTo(ProductMappingItem::class);
    }

}
