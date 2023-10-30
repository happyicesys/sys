<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Model;

class DeliveryProductMappingVend extends Model
{
    // use HasFactory;

    protected $table = 'delivery_product_mapping_vend';

    protected $fillable = [
        'delivery_product_mapping_id',
        'delivery_product_mapping_vend_channels_json',
        'is_active',
        'platform_ref_id',
        'vend_code',
        'vend_id',
    ];

    protected $casts = [
        'delivery_product_mapping_vend_channels_json' => 'json',
    ];

    // relationships
    public function deliveryProductMapping()
    {
        return $this->belongsTo(DeliveryProductMapping::class);
    }

    public function deliveryProductMappingVendChannels()
    {
        return $this->hasMany(DeliveryProductMappingVendChannel::class)->orderBy('vend_channel_code');
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}