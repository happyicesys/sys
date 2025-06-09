<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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
        self::TYPE_ITEM => 'Free Item',
        self::TYPE_PERCENT => 'Percent Discount',
        self::TYPE_AMOUNT => 'Absolute Amount Discount',
    ];


    // temporary hardcoded at both sides
    const DCVEND_MEMBER_TYPE_ALL = '1';
    const DCVEND_MEMBER_TYPE_FREE = '2';
    const DCVEND_MEMBER_TYPE_CONVERTED = '3';
    const DCVEND_MEMBER_TYPE_GOLD = '4';

    const DCVEND_MEMBER_TYPE_MAPPINGS = [
        self::DCVEND_MEMBER_TYPE_ALL => 'All Members',
        self::DCVEND_MEMBER_TYPE_FREE => 'Free Members',
        self::DCVEND_MEMBER_TYPE_CONVERTED => 'Converted Members',
        self::DCVEND_MEMBER_TYPE_GOLD => 'Gold Members',
    ];

    const RENEWAL_MODE_ONE_TIME = '0';
    const RENEWAL_MODE_RENEW_EVERY_MONTH = '1';

    const RENEWAL_MODE_MAPPINGS = [
        self::RENEWAL_MODE_ONE_TIME => 'One Time',
        self::RENEWAL_MODE_RENEW_EVERY_MONTH => 'Renew Every Month',
    ];

    const VOUCHER_MODE_ONE_TIME = '1';
    const VOUCHER_MODE_RECURRING = '2';

    const VOUCHER_MODE_MAPPINGS = [
        self::VOUCHER_MODE_ONE_TIME => 'One Time',
        self::VOUCHER_MODE_RECURRING => 'Recurring',
    ];

    const VOUCHER_PLATFORM_ALL = '1';
    const VOUCHER_PLATFORM_NORMAL = '2';
    const VOUCHER_PLATFORM_DCVEND = '3';

    const VOUCHER_PLATFORM_MAPPINGS = [
        self::VOUCHER_PLATFORM_ALL => 'All',
        self::VOUCHER_PLATFORM_NORMAL => 'Normal',
        self::VOUCHER_PLATFORM_DCVEND => 'DC Vend',
    ];

    const VALID_DURATION_MAPPINGS = [
        '1' => '1',
        '3' => '3',
        '5' => '5',
        '7' => '7',
        '14' => '14',
    ];

    const VALID_UNIT_MAPPINGS = [
        'day' => 'Day(s)',
        'month' => 'Month(s)',
    ];

    protected $fillable = [
        'code',
        'customer_id',
        'date_from',
        'date_to',
        'dcvend_member_type',
        'dcvend_qty_per_member',
        'desc',
        'is_active',
        'is_batch_code',
        'is_dcvend',
        'is_recurring',
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
        'used_qty',
        'valid_duration',
        'valid_unit',
        'value',
        'vend_id',
    ];

    protected $casts = [
        'date_from' => 'datetime',
        'date_to' => 'datetime',
        'is_active' => 'boolean',
        'is_batch_code' => 'boolean',
        'is_dcvend' => 'boolean',
        'product_json' => 'json',
        'response_json' => 'json',
    ];

    // getter and setter
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => strtoupper($value),
        );
    }

    protected function maxPromoValue(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value * 100,
            get: fn ($value) => $value / 100,
        );
    }

    protected function minValue(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value * 100,
            get: fn ($value) => $value / 100,
        );
    }

    protected function value(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value * 100,
            get: fn ($value) => $value / 100,
        );
    }

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

    // scopes
    public function scopeFilterIndex($query, $request)
    {
        return $query->when($request->name, function($query, $search) {
            $query->where('name', 'LIKE', "%{$search}%");
        })
        ->when($request->code, function($query, $search) {
            $query->where(function($query) use ($search) {
                $query->where('code', 'LIKE', "%{$search}%")
                    ->orWhereHas('voucherItems', function($query) use ($search) {
                        $query->where('code', 'LIKE', "%{$search}%");
                    });
            });
        })
        ->when($request->is_active, function($query, $search) {
            if($search !== 'all') {
                $query->where('is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
            }
        })
        ->when($request->is_batch_code, function($query, $search) {
            if($search !== 'all') {
                $query->where('is_batch_code', !filter_var($search, FILTER_VALIDATE_BOOLEAN));
            }
        })
        ->when($request->sortKey, function($query, $search) use ($request) {
            $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
        });
    }
}

