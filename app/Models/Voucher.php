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
        'used_qty',
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

    // getter and setter
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => strtoupper($value),
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

