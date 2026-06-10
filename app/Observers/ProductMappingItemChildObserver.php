<?php

namespace App\Observers;

/**
 * DEPRECATED / UNUSED.
 *
 * Blind SKU flavours moved from per-product-mapping to per-product. This
 * observer (and the ProductMappingItemChild model + product_mapping_item_children
 * table) are no longer used or registered — see ProductChildObserver instead.
 * Kept only as an empty stub because the file can't be deleted from here; safe
 * to remove from the repo.
 */
class ProductMappingItemChildObserver
{
    //
}
