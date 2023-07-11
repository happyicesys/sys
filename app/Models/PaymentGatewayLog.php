<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayLog extends Model
{
    const STATUS_PENDING = 1;
    const STATUS_APPROVE = 2;
    const STATUS_REFUND = 98;
    const STATUS_DECLINE = 99;

    use HasFactory;

    protected $fillable = [
        'amount',
        'error_json',
        'history_json',
        'request',
        'response',
        'order_id',
        'qr_url',
        'qr_text',
        'vend_transaction_id',
        'operator_payment_gateway_id',
        'payment_gateway_id',
        'ref_id',
        'request_history_json',
        'status',
        'vend_channel_code',
        'vend_channel_id',
        'vend_code',
        'vend_id',
    ];

    protected $casts = [
        'history_json' => 'json',
        'error_json' => 'json',
        'request' => 'json',
        'request_history_json' => 'json',
        'response' => 'json',
    ];

    public function operatorPaymentGateway()
    {
        return $this->belongsTo(OperatorPaymentGateway::class);
    }

    public function paymentGateway()
    {
        return $this->belongsTo(PaymentGateway::class);
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
