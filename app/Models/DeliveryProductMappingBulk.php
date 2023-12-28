<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class DeliveryProductMappingBulk extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_platform_campaign_id',
        'delivery_product_mapping_id',
        'group_json',
        'is_active',
        'name',
        'promo_desc',
        'promo_label',
        'promo_type',
        'promo_value',
        'total_qty',
    ];

    protected $casts = [
        'group_json' => 'json',
    ];

    // relationships
    public function deliveryPlatformCampaign()
    {
        return $this->belongsTo(DeliveryPlatformCampaign::class);
    }

    public function deliveryProductMapping()
    {
        return $this->belongsTo(DeliveryProductMapping::class);
    }

    public function deliveryProductMappingBulkItems()
    {
        return $this->hasMany(DeliveryProductMappingBulkItem::class);
    }

    public function thumbnail()
    {
        return $this->morphOne(Attachment::class, 'modelable');
    }
}
