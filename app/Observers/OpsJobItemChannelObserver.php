<?php

namespace App\Observers;

use App\Models\OpsJobItemChannel;
use App\Models\OpsJobItemChannelChild;
use App\Models\Product;
use App\Models\ProductChild;

/**
 * Blind SKU — snapshot the flavour set onto each ops job channel AT CREATION.
 *
 * When an ops_job_item_channels row is created, if its product is a blind housing
 * AT THAT MOMENT, we freeze the product's active flavours + weights into the
 * per-channel ledger (ops_job_item_channel_children).
 *
 * This makes every ops job self-describing: a job created while the product was a
 * normal SKU simply has no ledger rows (renders normally), and one created while
 * blind keeps its frozen flavour set. Toggling is_parent_sku on/off afterwards, or
 * editing the product's flavours later, never disturbs existing jobs — which the
 * fragile is_parent_sku_since date could not guarantee across multiple toggles.
 */
class OpsJobItemChannelObserver
{
    public function created(OpsJobItemChannel $channel): void
    {
        if (empty($channel->product_id)) {
            return;
        }

        $isParent = Product::withoutGlobalScopes()
            ->whereKey($channel->product_id)
            ->value('is_parent_sku');

        if (!$isParent) {
            return;
        }

        $children = ProductChild::where('parent_product_id', $channel->product_id)
            ->where('is_active', true)
            ->orderBy('sort')
            ->get();

        if ($children->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($children as $i => $child) {
            $rows[] = [
                'ops_job_item_channel_id' => $channel->id,
                'child_product_id' => $child->child_product_id,
                'weight_pct' => (int) $child->weight_pct,
                'to_pick_qty' => 0,
                'picked_qty' => 0,
                'actual_qty' => 0,
                'sort' => (int) ($child->sort ?? $i),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Defensive: a `created` event fires once, so there should be no rows yet;
        // insertOrIgnore keeps it safe against the (parent_id, child_id) unique key.
        OpsJobItemChannelChild::insertOrIgnore($rows);
    }
}
