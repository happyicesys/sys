<?php

namespace App\Observers;

use App\Jobs\RecomputeBlindCosts;
use App\Models\UnitCost;

/**
 * When a product's unit cost changes, any blind parent that uses that product
 * as a child must have its blended cost recomputed.
 *
 * CRITICAL: the derived blended rows are themselves UnitCost records (is_blended).
 * We MUST ignore those here, or recomputing (which writes a blended row) would
 * loop forever.
 */
class UnitCostObserver
{
    public function saved(UnitCost $unitCost): void
    {
        $this->fanOut($unitCost);
    }

    public function deleted(UnitCost $unitCost): void
    {
        $this->fanOut($unitCost);
    }

    private function fanOut(UnitCost $unitCost): void
    {
        // Ignore derived blended rows -> no recursion.
        if ($unitCost->is_blended) {
            return;
        }

        RecomputeBlindCosts::dispatch(childProductId: (int) $unitCost->product_id);
    }
}
