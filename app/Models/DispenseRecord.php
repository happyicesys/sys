<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispenseRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_vm_receive_dispense_signal',
        'order_id',
        'payment_gateway_log_id',
        'retries',
        'vend_code',
        'vend_id',
        'vend_transaction_id',
        'vend_transaction_time',
    ];

    protected $casts = [
        'is_vm_receive_dispense_signal' => 'boolean',
        'vend_transaction_time' => 'datetime',
    ];

    // relationships
    public function paymentGatewayLog()
    {
        return $this->belongsTo(PaymentGatewayLog::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
