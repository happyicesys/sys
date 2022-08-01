<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendingMachineTransaction extends Model
{
    use HasFactory;

    protected $casts = [
        'transaction_datetime' => 'datetime'
    ];

    protected $fillable = [
        'order_id',
        'transaction_datetime',
        'amount',
        'payment_method_id',
        'vending_machine_channel_id',
        'vending_machine_channel_error_id',
        'vending_machine_id'
    ];

    // relationships
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function vendingMachine()
    {
        return $this->belongsTo(VendingMachine::class);
    }

    public function vendingMachineChannel()
    {
        return $this->belongsTo(VendingMachineChannel::class);
    }

    public function vendingMachineChannelError()
    {
        return $this->belongsTo(VendingMachineChannelError::class);
    }
}
