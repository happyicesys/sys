<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatformLog extends Model
{
    use HasFactory;

    const STATUS_NEW = 1;
    const STATUS_PENDING = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_REFUND = 98;
    const STATUS_DECLINE = 99;

    use HasFactory;

    protected $fillable = [
        'amount',
        'delivery_platform_id',
        'delivery_platform_operator_id',
        'error_json',
        'order_completed_at',
        'order_created_at',
        'ref_id',
        'order_id',
        'order_json',
        'request_history_json',
        'response_history_json',
        'status',
        'vend_channel_code',
        'vend_channel_id',
        'vend_code',
        'vend_id',
    ];

    protected $casts = [
        'error_json' => 'json',
        'order_json' => 'json',
        'request_history_json' => 'json',
        'response_history_json' => 'json',
    ];

    public function deliveryPlatformOperator()
    {
        return $this->belongsTo(DeliveryPlatformOperator::class);
    }

    public function deliveryPlatform()
    {
        return $this->belongsTo(DeliveryPlatform::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendChannel()
    {
        return $this->belongsTo(VendChannel::class);
    }
}
