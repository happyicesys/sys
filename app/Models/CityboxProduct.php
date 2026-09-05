<?php

namespace App\Models;

use App\Services\Citybox\DTO\ChillerCatalogItem;
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
        'citybox_status', 'citybox_status_at',
    ];

    protected $casts = [
        'vision_imgs' => 'array',
        'is_delisted' => 'boolean',
        'citybox_status' => 'integer',
        'citybox_status_at' => 'datetime',
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

    /**
     * Enabled in CityBox's own portal. Their `status` is the only retirement
     * signal — a disabled SKU keeps appearing in `product_list` forever — so
     * anything that is not their explicit 1 counts as NOT enabled. A row we
     * have never seen on a catalog run (NULL) is not claimed either way.
     */
    public function isEnabledInCitybox(): bool
    {
        return $this->citybox_status === ChillerCatalogItem::STATUS_ENABLED;
    }

    public function scopeDisabledInCitybox(Builder $q): Builder
    {
        return $q->whereNotNull('citybox_status')
            ->where('citybox_status', '!=', ChillerCatalogItem::STATUS_ENABLED);
    }

    public function isMapped(): bool
    {
        return $this->product_id !== null;
    }
}
