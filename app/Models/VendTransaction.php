<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendTransaction extends Model
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
        'vend_channel_id',
        'vend_channel_error_id',
        'vend_id'
    ];

    // relationships
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendChannel()
    {
        return $this->belongsTo(VendChannel::class);
    }

    public function vendChannelError()
    {
        return $this->belongsTo(VendChannelError::class);
    }
}
