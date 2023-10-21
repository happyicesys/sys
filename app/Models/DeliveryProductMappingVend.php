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
        'vend_id',
        'is_active',
        'delivery_product_mapping_vend_channels_json',
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
        return $this->hasMany(DeliveryProductMappingVendChannel::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
