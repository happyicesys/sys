<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * When a SKU arrived on a machine (one row per vend + product). Drives the menu NEW badge;
 * see App\Services\Menu\NewSkuBadge.
 */
class VendProductArrival extends Model
{
    public const SOURCE_SEED = 'seed';

    public const SOURCE_MAPPING = 'mapping';

    public const SOURCE_RETURNED = 'returned';

    public const SOURCE_BACKFILL = 'backfill';

    protected $fillable = [
        'vend_id',
        'product_id',
        'arrived_at',
        'left_at',
        'source',
    ];

    protected $casts = [
        'arrived_at' => 'datetime',
        'left_at' => 'datetime',
    ];
}
