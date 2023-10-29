<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItemVendChannel extends Model
{
    use HasFactory;

    protected $fillable =[
        'delivery_platform_order_id',
        'delivery_platform_order_item_id',
        'delivery_product_mapping_item_id',
        'delivery_product_mapping_vend_channel_id',
        'qty',
        'vend_channel_code',
        'vend_channel_id',
    ];

    // relationships
    public function deliveryPlatformOrder()
    {
        return $this->belongsTo(DeliveryPlatformOrder::class);
    }

    public function deliveryPlatformOrderItem()
    {
        return $this->belongsTo(DeliveryPlatformOrderItem::class);
    }

    public function deliveryProductMappingItem()
    {
        return $this->belongsTo(DeliveryProductMappingItem::class);
    }

    public function deliveryProductMappingVendChannel()
    {
        return $this->belongsTo(DeliveryProductMappingVendChannel::class);
    }

    public function vendChannel()
    {
        return $this->belongsTo(VendChannel::class);
    }

}
