<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DEPRECATED / UNUSED.
 *
 * Blind SKU flavours moved from per-product-mapping to per-product. The backing
 * table `product_mapping_item_children` is dropped by the 2026_06_10 migration,
 * and nothing references this model. Use App\Models\ProductChild instead.
 * Kept only as an inert stub because the file can't be deleted from here; safe
 * to remove from the repo.
 */
class ProductMappingItemChild extends Model
{
    protected $table = 'product_mapping_item_children';
}
