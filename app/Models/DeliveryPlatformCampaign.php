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
        'min_amount',
        'name',
        'platform_ref_id',
        'total_redeemable_count',
        'total_redeemable_count_per_user',
        'user_type'
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
