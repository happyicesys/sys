<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProductMappingVendChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'order_qty',
        'delivery_product_mapping_id',
        'delivery_product_mapping_item_id',
        'delivery_product_mapping_vend_id',
        'is_active',
        'qty',
        'reserved_percent',
        'reserved_qty',
        'vend_channel_code',
        'vend_channel_id',
        'vend_code',
        'vend_id',
    ];

    // relationships
    public function deliveryProductMappingVend()
    {
        return $this->belongsTo(DeliveryProductMappingVend::class);
    }
}
