<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatformOrder extends Model
{
    use HasFactory;

    const STATUS_NEW = 1;
    const STATUS_ACCEPTED = 2;
    const STATUS_PENDING = 3;
    const STATUS_COMPLETED = 4;
    const STATUS_REFUNDED = 98;
    const STATUS_DECLINED = 99;

    protected $fillable = [
        'total_amount',
        'delivery_platform_id',
        'delivery_platform_operator_id',
        'driver_arrived_at',
        'driver_assigned_at',
        'error_json',
        'is_cancelled',
        'is_edited',
        'order_completed_at',
        'order_created_at',
        'ref_id',
        'order_id',
        'order_json',
        'request_history_json',
        'response_history_json',
        'short_order_id',
        'status',
        'vend_channel_code',
        'vend_channel_id',
        'vend_code',
        'vend_id',
    ];

    protected $casts = [
        'driver_arrived_at' => 'datetime',
        'driver_assigned_at' => 'datetime',
        'error_json' => 'json',
        'order_completed_at' => 'datetime',
        'order_created_at' => 'datetime',
        'order_json' => 'json',
        'request_history_json' => 'json',
        'response_history_json' => 'json',
    ];

    // relationships
    public function deliveryPlatform()
    {
        return $this->belongsTo(DeliveryPlatform::class);
    }

    public function deliveryPlatformOperator()
    {
        return $this->belongsTo(DeliveryPlatformOperator::class);
    }

    public function deliveryPlatformOrderItems()
    {
        return $this->hasMany(DeliveryPlatformOrderItem::class);
    }

    public function productMapping()
    {
        return $this->belongsTo(ProductMapping::class);
    }

    public function vendChannel()
    {
        return $this->belongsTo(VendChannel::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
