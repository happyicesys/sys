<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry of Zijia's SKU library, mirrored by ZijiaLibraryMirror. `product_code` is the code the
 * AI knows it by, so a product is linked to it when `products.barcode` equals it; nothing else
 * records the link. `dismissed_at` = a person said it is not ours.
 */
class ZijiaLibrarySku extends Model
{
    protected $fillable = [
        'sys_sku_id', 'product_code', 'sku_name', 'name_key', 'spec', 'brand_name',
        'library_updated_at', 'dismissed_at', 'dismissed_by',
    ];

    protected $casts = [
        'library_updated_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    /** Entries no product carries the code of. */
    public function scopeUnlinked(Builder $query): Builder
    {
        return $query->whereNotIn('product_code', Product::withoutGlobalScopes()->select('barcode')
            ->whereNotNull('barcode')->where('barcode', '!=', ''));
    }

    /**
     * Their library is shared by all their customers, and ours are named in English: an entry named
     * in Chinese is another customer's as far as a person's review goes. Exact names still match.
     */
    public function looksOurs(): bool
    {
        return ! preg_match('/[\x{4e00}-\x{9fff}]/u', (string) $this->sku_name);
    }
}
