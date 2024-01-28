<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 1;
    const STATUS_REDEEMED = 88;
    const STATUS_CANCELLED = 99;

    protected $fillable = [
        'code',
        'customer_id',
        'date_from',
        'date_to',
        'response_json',
        'status',
        'vend_id',
    ];

    protected $casts = [
        'date_from' => 'datetime',
        'date_to' => 'datetime',
        'response_json' => 'array',
    ];

    public function voucherItems()
    {
        return $this->hasMany(VoucherItem::class);
    }
}

