<?php

namespace App\Models;

use App\Models\DeliveryPlatforms\Grab;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class DeliveryPlatformOrder extends Model
{
    use HasFactory;

    const ORDER_EXPIRED_HOURS = 48;

    const STATUS_PENDING = 1;
    const STATUS_ACCEPTED = 2;
    const STATUS_ASSIGNED = 3;
    const STATUS_ARRIVED = 4;
    const STATUS_REQUESTED = 5;
    const STATUS_DISPENSED = 6;
    const STATUS_COLLECTED = 7;
    const STATUS_DELIVERED = 8;
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
        self::STATUS_REQUESTED => 'Requested',
        self::STATUS_DISPENSED => 'Dispensed',
        self::STATUS_COLLECTED => 'Collected',
        self::STATUS_DELIVERED => 'Delivered',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_FAILED => 'Failed',
    ];

    protected $fillable = [
        'campaign_json',
        'collected_datetime',
        'delivered_datetime',
        'delivery_platform_id',
        'delivery_platform_operator_id',
        'delivery_product_mapping_vend_id',
        'driver_phone_number',
        'driver_request_json',
        'error_json',
        'is_cancelled',
        'is_edited',
        'is_verified',
        'last_mile_timediff_mins',
        'order_created_at',
        'order_id',
        'order_json',
        'platform_ref_id',
        'promo_amount',
        'remarks',
        'request_history_json',
        'response_history_json',
        'short_order_id',
        'status',
        'status_json',
        'subtotal_amount',
        'total_amount',
        'vend_code',
        'vend_json',
        'vend_transaction_id',
        'vend_transaction_order_id',
        'virtual_campaign_id_json',
    ];

    protected $casts = [
        'campaign_json' => 'json',
        'collected_datetime' => 'datetime',
        'delivered_datetime' => 'datetime',
        'driver_request_json' => 'json',
        'error_json' => 'json',
        'order_created_at' => 'datetime',
        'order_json' => 'json',
        'request_history_json' => 'json',
        'response_history_json' => 'json',
        'status_json' => 'json',
        'vend_json' => 'json',
        'virtual_campaign_id_json' => 'json',
    ];

    // getter and setter
    protected function promoAmount(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value/ ($this->deliveryProductMappingVend->deliveryProductMapping && $this->deliveryProductMappingVend->deliveryProductMapping->operator ? pow(10, $this->deliveryProductMappingVend->deliveryProductMapping->operator->country->currency_exponent) : 100),
        );
    }

    protected function subtotalAmount(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value/ ($this->deliveryProductMappingVend->deliveryProductMapping && $this->deliveryProductMappingVend->deliveryProductMapping->operator ? pow(10, $this->deliveryProductMappingVend->deliveryProductMapping->operator->country->currency_exponent) : 100),
        );
    }

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

    // scopes
    public function scopeFilterIndex($query, $request)
    {

        $query = $query
        ->when($request->delivery_platform_operator_id, function($query, $search) {
            if($search != 'all') {
                $query->where('delivery_platform_operator_id', $search);
            }
        })
        ->when($request->order_id, function($query, $search) {
            $query->where('order_id', 'LIKE', "%{$search}%");
        })
        ->when($request->short_order_id, function($query, $search) {
            $query->where('short_order_id', 'LIKE', "%{$search}%");
        })
        ->when($request->vend_code, function($query, $search) use ($request) {
            $query->whereHas('deliveryProductMappingVend.vend', function($query) use ($request) {
                $query->where('code', 'LIKE', "{$request->vend_code}%");
            });
        })
        ->when($request->date_from, function ($query, $search) {
            $query->where('order_created_at', '>=', Carbon::parse($search)->startOfDay());
        })
        ->when($request->date_to, function ($query, $search) {
            $query->where('order_created_at', '<=', Carbon::parse($search)->endOfDay());
        })
        ->when($request->status, function ($query, $search) {
            if($search != 'all') {
                $query->where('status', $search);
            }
        })
        ->when($request->has_complaint, function ($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->has('deliveryPlatformOrderComplaint');
                }else {
                    $query->doesntHave('deliveryPlatformOrderComplaint');
                }
            }
        });

        return $query;
    }
}
