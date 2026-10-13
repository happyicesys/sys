<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One product's price in one CityBox price group, as `price_group_list` last reported it. */
class CityboxPriceGroup extends Model
{
    protected $fillable = [
        'group_id', 'group_name', 'citybox_product_id', 'product_id', 'price_cents', 'img_url',
        'first_seen_at', 'last_seen_at', 'removed_at',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeCurrent(Builder $q): Builder
    {
        return $q->whereNull('removed_at');
    }
}
