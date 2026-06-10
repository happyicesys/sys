<?php

namespace App\Observers;

use App\Jobs\RecomputeBlindCosts;
use App\Models\ProductChild;

/**
 * Any change to a blind parent's flavours/weights re-blends that parent's cost.
 */
class ProductChildObserver
{
    public function saved(ProductChild $child): void
    {
        RecomputeBlindCosts::dispatch(parentIds: [(int) $child->parent_product_id]);
    }

    public function deleted(ProductChild $child): void
    {
        RecomputeBlindCosts::dispatch(parentIds: [(int) $child->parent_product_id]);
    }
}
