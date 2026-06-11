<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductChild;
use Illuminate\Support\Collection;

/**
 * Warehouse-planning attribution for blind SKUs.
 *
 * A blind housing's planning quantities (To-Pick / Picked / Daily Sold) are
 * recorded against the housing, because that's the channel-facing SKU. But the
 * real warehouse impact is on the flavours. This splits each housing's figures
 * down to its flavours by ratio and ADDS them onto the displayed child rows, so
 * the flavours forecast correctly. Housing rows keep their own figures (shown
 * greyed and already excluded from the subtotal).
 *
 * Performance: bounded work only — exactly two extra queries (child sets +
 * parent/child base info), independent of the number of displayed products. The
 * parent To-Pick/Picked values come from caller-provided maps the page already
 * computed (extended to cover parents), so there is no per-row querying here.
 */
class BlindPlanningService
{
    /**
     * @param  iterable  $products      displayed products (Eloquent models, mutated in place)
     * @param  array|Collection  $neededByPid  product_id => To-Pick value (MUST include parents)
     * @param  array|Collection  $pickedByPid  product_id => Picked value (MUST include parents)
     * @param  string  $pickedField   the product attribute the Picked bonus is added to
     */
    public static function attributeToChildren($products, $neededByPid, $pickedByPid, string $pickedField): void
    {
        $neededByPid = collect($neededByPid);
        $pickedByPid = collect($pickedByPid);

        // Parents referenced by the displayed flavours (robust: works even when the
        // housing itself isn't in the current result set).
        $parentIds = collect();
        foreach ($products as $p) {
            if ($p->relationLoaded('blindParentLinks')) {
                foreach ($p->blindParentLinks as $link) {
                    $parentIds->push((int) $link->parent_product_id);
                }
            }
        }
        $parentIds = $parentIds->unique()->values();
        if ($parentIds->isEmpty()) {
            return;
        }

        // (Query 1) every parent's full active flavour set + weights.
        $links = ProductChild::query()
            ->whereIn('parent_product_id', $parentIds)
            ->where('is_active', true)
            ->get(['parent_product_id', 'child_product_id', 'weight_pct', 'sort']);
        if ($links->isEmpty()) {
            return;
        }
        $linksByParent = $links->groupBy('parent_product_id');

        // (Query 2) avg-7d + availability for parents and all their flavours.
        $baseIds = $parentIds->merge($links->pluck('child_product_id'))->unique()->values();
        $base = Product::withoutGlobalScopes()
            ->whereIn('id', $baseIds)
            ->get(['id', 'avg_seven_days_count', 'is_available'])
            ->keyBy('id');

        $bonusNeeded = [];
        $bonusPicked = [];
        $bonusDaily = [];

        foreach ($parentIds as $pid) {
            $kids = $linksByParent->get($pid);
            if (!$kids || $kids->isEmpty()) {
                continue;
            }

            // Match the page gate: an unavailable housing contributes 0 To-Pick.
            $parentAvailable = (bool) (optional($base->get($pid))->is_available ?? false);
            $parentNeeded = $parentAvailable ? (int) round((float) $neededByPid->get($pid, 0)) : 0;
            $parentPicked = (int) round((float) $pickedByPid->get($pid, 0));
            $parentDaily  = (float) (optional($base->get($pid))->avg_seven_days_count ?? 0);

            $allocInput = $kids->map(fn ($k) => [
                'key' => (int) $k->child_product_id,
                'weight' => (int) $k->weight_pct,
                'available' => (bool) (optional($base->get($k->child_product_id))->is_available ?? true),
                'cap' => null,
                'sort' => (int) $k->sort,
            ])->all();

            // Whole-unit metrics use the tested Largest-Remainder allocator (conserves
            // the total exactly, redistributes away from unavailable flavours).
            $neededAlloc = BlindSkuService::allocateToPick(max(0, $parentNeeded), $allocInput);
            $pickedAlloc = BlindSkuService::allocateToPick(max(0, $parentPicked), $allocInput);

            // Daily-sold is an average -> split proportionally (float) over available flavours.
            $eligibleWeight = 0;
            foreach ($kids as $k) {
                if (optional($base->get($k->child_product_id))->is_available ?? true) {
                    $eligibleWeight += (int) $k->weight_pct;
                }
            }

            foreach ($kids as $k) {
                $cid = (int) $k->child_product_id;
                $bonusNeeded[$cid] = ($bonusNeeded[$cid] ?? 0) + ($neededAlloc[$cid] ?? 0);
                $bonusPicked[$cid] = ($bonusPicked[$cid] ?? 0) + ($pickedAlloc[$cid] ?? 0);
                if ($eligibleWeight > 0 && (optional($base->get($cid))->is_available ?? true)) {
                    $bonusDaily[$cid] = ($bonusDaily[$cid] ?? 0) + $parentDaily * ((int) $k->weight_pct / $eligibleWeight);
                }
            }
        }

        // Add the blind share onto each displayed flavour row.
        foreach ($products as $p) {
            $cid = (int) $p->id;
            if (isset($bonusNeeded[$cid])) {
                $p->needed_qty = (int) round((float) ($p->needed_qty ?? 0) + $bonusNeeded[$cid]);
            }
            if (isset($bonusPicked[$cid])) {
                $p->{$pickedField} = (int) round((float) ($p->{$pickedField} ?? 0) + $bonusPicked[$cid]);
            }
            if (isset($bonusDaily[$cid])) {
                $p->avg_seven_days_count = (float) ($p->avg_seven_days_count ?? 0) + $bonusDaily[$cid];
            }
        }
    }
}
