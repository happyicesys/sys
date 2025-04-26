<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 1;
    const STATUS_REDEEMED = 2;
    const STATUS_EXPIRED = 88;

    const TYPE_ITEM = 'item';
    const TYPE_PERCENT = 'percent';
    const TYPE_AMOUNT = 'amount';

    const STATUS_MAPPINGS = [
        self::STATUS_ACTIVE => 'active',
        self::STATUS_REDEEMED => 'redeemed',
        self::STATUS_EXPIRED => 'expired',
    ];

    const TYPE_MAPPINGS = [
        self::TYPE_ITEM => 'Item',
        self::TYPE_PERCENT => 'Percent',
        self::TYPE_AMOUNT => 'Amount',
    ];

    protected $fillable = [
        'code',
        'customer_id',
        'date_from',
        'date_to',
        'desc',
        'is_active',
        'is_batch_code',
        'max_promo_value',
        'max_redemption_count',
        'min_value',
        'name',
        'operator_id',
        'product_json',
        'qty',
        'response_json',
        'status',
        'type',
        'value',
        'vend_id',
    ];

    protected $casts = [
        'date_from' => 'datetime',
        'date_to' => 'datetime',
        'is_active' => 'boolean',
        'is_batch_code' => 'boolean',
        'product_json' => 'json',
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

    public function voucherItems()
    {
        return $this->hasMany(VoucherItem::class);
    }
}

