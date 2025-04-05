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
        'desc',
        'is_active',
        'is_batch_code',
        'is_redeemed',
        'max_promo_value',
        'max_redemption_count',
        'member_id',
        'min_value',
        'name',
        'product_json',
        'qty',
        'redeemed_at',
        'response_json',
        'status',
        'vend_id',
    ];

    protected $casts = [
        'date_from' => 'datetime',
        'date_to' => 'datetime',
        'is_active' => 'boolean',
        'is_batch_code' => 'boolean',
        'is_redeemed' => 'boolean',
        'product_json' => 'json',
        'redeemed_at' => 'datetime',
        'response_json' => 'json',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vend()
    {
        return $this->hasMany(Vend::class);
    }
}

