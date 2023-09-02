<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempVendTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'ref_id',
        'vend_code',
        'transaction_datetime',
        'transaction_datetime_ref',
        'channel_code',
        'ref_payment_method_id',
        'amount',
    ];

    protected $casts = [
        'transaction_datetime' => 'datetime',
    ];
}
