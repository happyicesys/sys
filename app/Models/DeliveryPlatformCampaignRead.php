<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** When a store's Grab campaign list was last read, and how it went. */
class DeliveryPlatformCampaignRead extends Model
{
    protected $fillable = [
        'delivery_platform_ref_number_id',
        'last_message',
        'last_ok_at',
        'last_read_at',
        'last_status',
        'merchant_id',
        'ongoing_count',
        'upcoming_count',
    ];

    protected $casts = [
        'last_ok_at' => 'datetime',
        'last_read_at' => 'datetime',
    ];
}
