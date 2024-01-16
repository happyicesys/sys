<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatformCampaignItemVend extends Model
{
    use HasFactory;

    protected $fillable = [
        'datetime_from',
        'datetime_to',
        'delivery_platform_campaign_id',
        'delivery_platform_campaign_item_id',
        'delivery_product_mapping_vend_id',
        'is_active',
        'is_submitted',
        'platform_ref_id',
        'settings_json',
        'settings_label',
        'settings_name',
        'submission_response_json',
        'vend_code',
    ];

    protected $casts = [
        'datetime_from' => 'datetime',
        'datetime_to' => 'datetime',
        'settings_json' => 'json',
        'submission_response_json' => 'json',
    ];

    // relationships
    public function deliveryPlatformCampaign()
    {
        return $this->belongsTo(DeliveryPlatformCampaign::class);
    }

    public function deliveryPlatformCampaignItem()
    {
        return $this->belongsTo(DeliveryPlatformCampaignItem::class);
    }

    public function deliveryProductMappingVend()
    {
        return $this->belongsTo(DeliveryProductMappingVend::class);
    }
}
