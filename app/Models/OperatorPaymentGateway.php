<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperatorPaymentGateway extends Model
{
    use HasFactory;

    const TYPE_SANDBOX = 'sandbox';
    const TYPE_PRODUCTION = 'production';

    protected $fillable = [
        'operator_id',
        'payment_gateway_id',
        'key1',
        'key2',
        'key3',
        'type',
    ];

    // relationships
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function paymentGateway()
    {
        return $this->belongsTo(PaymentGateway::class);
    }

}
