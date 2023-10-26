<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatformMenuRecord extends Model
{
    use HasFactory;

    const TYPE_ACTIVE = 1;
    const TYPE_PASSIVE = 2;

    const TYPE_MAPPING = [
        self::TYPE_ACTIVE => 'Active',
        self::TYPE_PASSIVE => 'Passive',
    ];

    protected $fillable = [
        'delivery_platform_operator_id',
        'delivery_platform_slug',
        'delivery_product_mapping_vend_id',
        'menu_json',
        'platform_ref_id',
        'platform_ref_json',
        'request_datetime',
        'remarks',
        'type',
    ];

    protected $casts = [
        'menu_json' => 'json',
        'platform_ref_json' => 'json',
    ];

    // relationships
    public function deliveryPlatformOperator()
    {
        return $this->belongsTo(DeliveryPlatformOperator::class);
    }

    public function deliveryProductMappingVend()
    {
        return $this->belongsTo(DeliveryProductMappingVend::class);
    }
}
