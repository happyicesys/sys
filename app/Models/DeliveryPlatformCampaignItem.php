<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatformCampaignItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_platform_campaign_id',
        'is_active',
        'items_json',
        'settings_json',
        'settings_label',
        'settings_name',
    ];

    protected $casts = [
        'items_json' => 'json',
        'settings_json' => 'json',
    ];

    // relationships
    public function deliveryPlatformCampaign()
    {
        return $this->belongsTo(DeliveryPlatformCampaign::class);
    }

    public function deliveryPlatformCampaignItemVends()
    {
        return $this->hasMany(DeliveryPlatformCampaignItemVend::class);
    }
}
