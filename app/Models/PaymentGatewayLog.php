<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayLog extends Model
{
    const STATUS_PENDING = 1;
    const STATUS_APPROVE = 2;
    const STATUS_DECLINE = 99;

    use HasFactory;

    protected $fillable = [
        'request',
        'response',
        'order_id',
        'vend_transaction_id',
        'operator_payment_gateway_id',
        'payment_gateway_id',
        'amount',
    ];

    protected $casts = [
        'request' => 'json',
        'response' => 'json',
    ];
}
