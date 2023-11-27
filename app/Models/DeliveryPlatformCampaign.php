<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatformCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_product_mapping_id',
        'datetime_from',
        'datetime_to',
        'desc',
        'name',
        'platform_campaign_type',
        'platform_campaign_scope',
        'platform_campaign_value',
    ];

    protected $casts = [
        'datetime_from' => 'datetime',
        'datetime_to' => 'datetime',
    ];

    // relationships
    public function deliveryProductMapping()
    {
        return $this->belongsTo(DeliveryProductMapping::class);
    }
}
