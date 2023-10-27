<?php

namespace App\Models;

use App\Models\DeliveryPlatforms\Grab;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatformOrder extends Model
{
    use HasFactory;

    const STATUS_PENDING = 1;
    const STATUS_ACCEPTED = 2;
    const STATUS_ASSIGNED = 3;
    const STATUS_ARRIVED = 4;
    const STATUS_COLLECTED = 5;
    const STATUS_DELIVERED = 6;
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

    protected $fillable = [
        'delivery_platform_id',
        'delivery_platform_operator_id',
        'delivery_product_mapping_vend_id',
        'driver_arrived_at',
        'driver_assigned_at',
        'driver_eta_seconds',
        'driver_eta_updated_at',
        'error_json',
        'is_cancelled',
        'is_edited',
        'order_completed_at',
        'order_created_at',
        'ref_id',
        'order_id',
        'order_json',
        'platform_ref_id',
        'request_history_json',
        'response_history_json',
        'short_order_id',
        'status',
        'subtotal_amount',
        'total_amount',
        'vend_code',
        'vend_id',
    ];

    protected $casts = [
        'driver_arrived_at' => 'datetime',
        'driver_assigned_at' => 'datetime',
        'driver_eta_updated_at' => 'datetime',
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

    public function deliveryProductMappingVend()
    {
        return $this->belongsTo(DeliveryProductMappingVend::class);
    }

    public function deliveryProductMappingVendChannel()
    {
        return $this->belongsTo(DeliveryProductMappingVendChannel::class);
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
