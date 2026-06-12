<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    /**
     * Cache key for the singleton settings row (read on every request by
     * HandleInertiaRequests). Flushed automatically on save/delete below so
     * the cached copy can never go stale.
     */
    const SINGLETON_CACHE_KEY = 'setting_singleton_row';

    protected static function booted()
    {
        static::saved(fn () => Cache::forget(self::SINGLETON_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::SINGLETON_CACHE_KEY));
    }

    /**
     * The single settings row, cached. Identical result to
     * Setting::query()->first() — just without the per-request query.
     */
    public static function singleton(): ?self
    {
        return Cache::remember(self::SINGLETON_CACHE_KEY, 600, fn () => static::query()->first());
    }

    // customer index view
    const CUSTOMER_INDEX = [
        'actual_stock_in_value' => [
            'red' => [
                'operator' => '<',
                'value' => 150
            ]
        ]
    ];

    protected $fillable = [
        'access_all_operator_ids_array',
        'allow_overwrite_logo_operator_ids_array',
        'customer_index_json',
        'payment_gateway_log_refund_scanned_at',
    ];

    protected $casts = [
        'access_all_operator_ids_array' => 'array',
        'allow_overwrite_logo_operator_ids_array' => 'array',
        'customer_index_json' => 'json',
        'payment_gateway_log_refund_scanned_at' => 'datetime'
    ];
}
