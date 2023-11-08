<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatformOrderComplaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_platform_order_id',
        'driver_phone_number',
        'original_json',
        'remarks'
    ];

    protected $casts = [
        'original_json' => 'json'
    ];

    // relationships
    public function deliveryPlatformOrder()
    {
        return $this->belongsTo(DeliveryPlatformOrder::class);
    }
}
