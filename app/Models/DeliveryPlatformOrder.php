<?php

namespace App\Models;

use App\Models\DeliveryPlatforms\Grab;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class DeliveryPlatformOrder extends Model
{
    use HasFactory;

    const DEFAULT_VALID_COLLECTION_HOURS = 2;

    const STATUS_PENDING = 1;
    const STATUS_ACCEPTED = 2;
    const STATUS_ASSIGNED = 3;
    const STATUS_ARRIVED = 4;
    const STATUS_DISPENSED = 5;
    const STATUS_COLLECTED = 6;
    const STATUS_DELIVERED = 7;
    const STATUS_CANCELLED = 98;
    const STATUS_FAILED = 99;

    const GRAB_STATUS_MAPPING = [
        Grab::STATE_PENDING => self::STATUS_PENDING,
        Grab::STATE_ACCEPTED => self::STATUS_ACCEPTED,
        Grab::STATE_DRIVER_ALLOCATED => self::STATUS_ASSIGNED,
        Grab::STATE_DRIVER_ARRIVED => self::STATUS_ARRIVED,
        Grab::STATE_COLLECTED => self::STATUS_COLLECTED,
        Grab::STATE_DELIVERED => self::STATUS_DELIVERED,
        Grab::STATE_CANCELLED => self::STATUS_CANCELLED,
        Grab::STATE_FAILED => self::STATUS_FAILED,
    ];

    const STATUS_MAPPING = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_ACCEPTED => 'Accepted',
        self::STATUS_ASSIGNED => 'Assigned',
        self::STATUS_ARRIVED => 'Arrived',
        self::STATUS_DISPENSED => 'Dispensed',
        self::STATUS_COLLECTED => 'Collected',
        self::STATUS_DELIVERED => 'Delivered',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_FAILED => 'Failed',
    ];

    protected $fillable = [
        'cancelled_json',
        'delivery_platform_id',
        'delivery_platform_operator_id',
        'delivery_product_mapping_vend_id',
        'driver_arrived_at',
        'driver_assigned_at',
        'driver_eta_seconds',
        'driver_eta_updated_at',
        'driver_phone_number',
        'driver_request_json',
        'error_json',
        'is_cancelled',
        'is_edited',
        'is_verified',
        'order_completed_at',
        'order_created_at',
        'order_id',
        'ref_id',
        'order_json',
        'platform_ref_id',
        'remarks',
        'request_history_json',
        'response_history_json',
        'short_order_id',
        'status',
        'subtotal_amount',
        'total_amount',
        'vend_code',
        'vend_json',
        'vend_transaction_id',
        'vend_transaction_order_id',
    ];

    protected $casts = [
        'cancelled_json' => 'json',
        'driver_arrived_at' => 'datetime',
        'driver_assigned_at' => 'datetime',
        'driver_eta_updated_at' => 'datetime',
        'driver_request_json' => 'json',
        'error_json' => 'json',
        'order_completed_at' => 'datetime',
        'order_created_at' => 'datetime',
        'order_json' => 'json',
        'request_history_json' => 'json',
        'response_history_json' => 'json',
        'vend_json' => 'json',
    ];

    // getter and setter
    // protected function subtotalAmount(): Attribute
    // {
    //     return Attribute::make(
    //         get: fn (string $value) => $value/ ($this->deliveryProductMappingVend->deliveryProductMappin && $this->deliveryProductMappingVend->deliveryProductMapping->operator ? pow(10, $this->deliveryProductMappingVend->deliveryProductMapping->operator->country->currency_exponent) : 100),
    //     );
    // }

    // relationships
    public function deliveryPlatform()
    {
        return $this->belongsTo(DeliveryPlatform::class);
    }

    public function deliveryPlatformOperator()
    {
        return $this->belongsTo(DeliveryPlatformOperator::class);
    }

    public function deliveryPlatformOrderComplaint()
    {
        return $this->hasOne(DeliveryPlatformOrderComplaint::class);
    }

    public function deliveryPlatformOrderItems()
    {
        return $this->hasMany(DeliveryPlatformOrderItem::class);
    }

    public function deliveryProductMappingVend()
    {
        return $this->belongsTo(DeliveryProductMappingVend::class);
    }

    public function orderItemVendChannels()
    {
        return $this->hasMany(OrderItemVendChannel::class);
    }

    public function vendTransaction()
    {
        return $this->belongsTo(VendTransaction::class);
    }
}
