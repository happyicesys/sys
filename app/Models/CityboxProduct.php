<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mirror of one CityBox SKU + its (human-set) link to a mark1 product.
 * Plain Eloquent: relationships, casts, scopes — no business logic.
 */
class CityboxProduct extends Model
{
    public const SOURCE_CATALOG = 'catalog';

    public const SOURCE_DEVICE = 'device';

    protected $fillable = [
        'citybox_product_id', 'product_id', 'name', 'sku_code', 'img_url', 'vision_imgs',
        'volume', 'unit', 'class_id', 'class_name', 'last_price_cents',
        'first_seen_at', 'last_seen_at', 'last_seen_source', 'is_delisted', 'mapped_at', 'mapped_by',
    ];

    protected $casts = [
        'vision_imgs' => 'array',
        'is_delisted' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'mapped_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function mappedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mapped_by');
    }

    public function scopeUnmapped(Builder $q): Builder
    {
        return $q->whereNull('product_id')->where('is_delisted', false);
    }

    public function scopeMapped(Builder $q): Builder
    {
        return $q->whereNotNull('product_id')->where('is_delisted', false);
    }

    public function scopeDelisted(Builder $q): Builder
    {
        return $q->where('is_delisted', true);
    }

    public function isMapped(): bool
    {
        return $this->product_id !== null;
    }
}
