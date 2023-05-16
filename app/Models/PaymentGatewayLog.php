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
        'request',
        'response',
        'order_id',
        'qr_url',
        'vend_transaction_id',
        'operator_payment_gateway_id',
        'payment_gateway_id',
        'amount',
        'status',
    ];

    protected $casts = [
        'request' => 'json',
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
}
