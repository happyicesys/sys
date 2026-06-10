<?php

namespace App\Services;

use App\Models\OpsJobItemChannelChild;
use App\Models\Product;
use App\Models\UnitCost;
use Illuminate\Support\Facades\DB;

/**
 * Blind SKU (parent/child housing) core logic.
 *
 * Two responsibilities, kept deliberately separate:
 *   1. PURE math (no DB): allocateToPick() and blendedCostCents(). These are the
 *      heart of the feature and are exhaustively unit-tested. Nothing in here
 *      touches Eloquent, so the corner cases are provable in isolation.
 *   2. DB glue: recomputeForParent() / recomputeForChildProduct() maintain the
 *      one-per-product blended unit_costs row used by the gross-profit pipeline.
 */
class BlindSkuService
{
    /* ===================================================================
     * 1. PURE MATH
     * =================================================================== */

    /**
     * Distribute $needed whole units across blind children by weight, using the
     * Largest-Remainder (Hamilton) method, with iterative water-filling so that
     * an unavailable / capped child's share flows to the remaining children.
     *
     * @param  int  $needed  whole units to pick for the housing (>= 0)
     * @param  array<int,array{key:mixed,weight:int,available?:bool,cap?:int|null,sort?:int}>  $children
     *         key       unique id for the child (returned in the result map)
     *         weight    integer weight (e.g. weight_pct). <= 0 => never allocated
     *         available default true. false => excluded (gets 0)
     *         cap       per-child max units (null/absent => uncapped). 0 => excluded
     *         sort      tie-break for leftover distribution (asc). default 0
     * @return array<mixed,int>  key => allocated whole units. Keys preserve input order.
     */
    public static function allocateToPick(int $needed, array $children): array
    {
        // Stable, deterministic result map seeded to 0 for every input child.
        $result = [];
        foreach ($children as $c) {
            $result[$c['key']] = 0;
        }

        if ($needed <= 0) {
            return $result;
        }

        // Normalise + filter to eligible children.
        $eligible = [];
        foreach ($children as $i => $c) {
            $available = $c['available'] ?? true;
            $cap = array_key_exists('cap', $c) ? $c['cap'] : null;
            $weight = (int) ($c['weight'] ?? 0);
            if (!$available || $weight <= 0 || ($cap !== null && $cap <= 0)) {
                continue; // excluded -> stays 0
            }
            $eligible[$c['key']] = [
                'key' => $c['key'],
                'weight' => $weight,
                'cap' => $cap,
                'sort' => (int) ($c['sort'] ?? 0),
                'order' => $i, // final, fully-deterministic tie-break
            ];
        }

        if (empty($eligible)) {
            return $result; // nobody can take it
        }

        $fixed = []; // keys clamped at their cap

        // Water-fill: each pass allocates the still-unmet remainder across the
        // unfixed pool; any child that exceeds its cap is clamped + fixed, and we
        // loop to redistribute what it couldn't take. Terminates because every
        // overflow pass fixes >= 1 child.
        while (true) {
            $pool = array_filter($eligible, fn ($c) => !isset($fixed[$c['key']]));
            if (empty($pool)) {
                break;
            }

            $remaining = $needed;
            foreach ($result as $v) {
                $remaining -= $v;
            }
            if ($remaining <= 0) {
                break;
            }

            $alloc = self::largestRemainder($remaining, $pool);

            $overflow = false;
            foreach ($pool as $key => $c) {
                $tentative = $result[$key] + $alloc[$key];
                if ($c['cap'] !== null && $tentative > $c['cap']) {
                    $result[$key] = $c['cap'];
                    $fixed[$key] = true;
                    $overflow = true;
                }
            }

            if (!$overflow) {
                // No caps breached this pass -> commit and finish.
                foreach ($pool as $key => $c) {
                    $result[$key] += $alloc[$key];
                }
                break;
            }
            // else: capped children are clamped; loop redistributes the rest.
        }

        return $result;
    }

    /**
     * Largest-Remainder split of $n whole units across $pool by integer weight.
     * Pure integer arithmetic (no floats) so it is exactly reproducible.
     *
     * @param  array<mixed,array{key:mixed,weight:int,sort:int,order:int}>  $pool
     * @return array<mixed,int>
     */
    private static function largestRemainder(int $n, array $pool): array
    {
        $W = 0;
        foreach ($pool as $c) {
            $W += $c['weight'];
        }

        $base = [];
        $rows = [];
        $used = 0;
        foreach ($pool as $key => $c) {
            $share = intdiv($n * $c['weight'], $W); // floor
            $base[$key] = $share;
            $used += $share;
            $rows[] = [
                'key' => $key,
                'remainder' => ($n * $c['weight']) - ($share * $W), // bigger => stronger claim
                'weight' => $c['weight'],
                'sort' => $c['sort'],
                'order' => $c['order'],
            ];
        }

        $leftover = $n - $used; // 0 .. count-1

        // Order leftover claims: largest remainder, then larger weight, then
        // smaller sort, then original order. Fully deterministic.
        usort($rows, function ($a, $b) {
            return [$b['remainder'], $b['weight'], $a['sort'], $a['order']]
               <=> [$a['remainder'], $a['weight'], $b['sort'], $b['order']];
        });

        for ($i = 0; $i < $leftover; $i++) {
            $base[$rows[$i]['key']]++;
        }

        return $base;
    }

    /**
     * Weighted-average blended cost in CENTS.
     *
     * @param  array<int,array{weight:int,cost_cents:int}>  $components  active children only
     * @return int  rounded blended cost in cents (0 if no weight)
     */
    public static function blendedCostCents(array $components): int
    {
        $weighted = 0;
        $totalWeight = 0;
        foreach ($components as $c) {
            $w = (int) $c['weight'];
            $weighted += $w * (int) $c['cost_cents'];
            $totalWeight += $w;
        }
        if ($totalWeight <= 0) {
            return 0;
        }
        return intdiv($weighted + intdiv($totalWeight, 2), $totalWeight); // round half up
    }

    /* ===================================================================
     * 2. DB GLUE  (one blended unit_costs row per blind parent product)
     * =================================================================== */

    /**
     * Recompute the blended unit_costs row for one blind parent product.
     * The blend is global to the product (reused across every mapping), so the
     * parent carries a single current unit cost like any other product.
     *
     * Returns: 'updated' (new current blended row written), 'unchanged' (blend
     * equals the existing current row), or 'incomplete' (a child lacks a current
     * unit cost -> no row written; the parent must not go live until fixed).
     */
    public static function recomputeForParent(Product $parent): string
    {
        $parent->loadMissing(['activeBlindChildren.childProduct.latestUnitCost']);

        if (!$parent->is_parent_sku) {
            return 'unchanged';
        }

        $components = [];
        $profileId = null;
        foreach ($parent->activeBlindChildren as $child) {
            $current = $child->childProduct?->latestUnitCost;
            if (!$current) {
                return 'incomplete'; // missing child cost -> cannot blend safely
            }
            $profileId = $profileId ?? $current->profile_id;
            $components[] = [
                'weight' => (int) $child->weight_pct,
                'cost_cents' => (int) round($current->cost * 100), // accessor returns dollars
            ];
        }

        if (empty($components)) {
            return 'incomplete';
        }

        $blendedCents = self::blendedCostCents($components);
        $parentId = $parent->id;

        return DB::transaction(function () use ($parentId, $blendedCents, $profileId) {
            $existing = UnitCost::withoutGlobalScopes()
                ->where('product_id', $parentId)
                ->where('is_current', true)
                ->lock()
                ->first();

            $existingCents = $existing ? (int) round($existing->cost * 100) : null;
            if ($existingCents === $blendedCents) {
                return 'unchanged';
            }

            $now = now();
            if ($existing) {
                $existing->is_current = false;
                $existing->date_to = $now;
                $existing->save();
            }

            $new = new UnitCost([
                'product_id' => $parentId,
                'profile_id' => $profileId,
                'date_from' => $now,
                'is_current' => true,
                'is_blended' => true,
            ]);
            // Bypass the *100 mutator drift: write the exact integer cents.
            $new->cost = $blendedCents / 100;
            $new->save();

            return 'updated';
        });
    }

    /**
     * Fan-out entry point: a product's unit cost changed -> recompute every
     * blind parent that uses it as a child. One indexed query gathers the parents.
     *
     * @return array{updated:int,incomplete:int,unchanged:int}
     */
    public static function recomputeForChildProduct(int $childProductId): array
    {
        $parentIds = DB::table('product_children')
            ->where('child_product_id', $childProductId)
            ->where('is_active', true)
            ->pluck('parent_product_id')
            ->unique();

        return self::recomputeParents($parentIds->all());
    }

    /* ===================================================================
     * 3. OPSJOB — per-child To-Pick ledger (virtual child ledger, Option A)
     * =================================================================== */

    /**
     * Compute + persist the per-flavour To-Pick ledger for one blind parent
     * channel. Clean seam: the OpsJob layer gathers the flavour context (weights,
     * per-child availability + cap) and the parent's needed qty, and calls this.
     *
     * Re-syncing only updates to_pick_qty / weight snapshot — it never clobbers
     * picked_qty / actual_qty already recorded by the driver.
     *
     * @param  int  $opsJobItemChannelId  the parent slot's ops_job_item_channels.id
     * @param  int  $parentNeeded         capacity - current vmc qty (the housing's need)
     * @param  array<int,array{child_product_id:int,weight:int,available?:bool,cap?:int|null,sort?:int}>  $childrenInput
     * @return array<int,int>  child_product_id => suggested to_pick qty
     */
    public static function syncChannelChildAllocations(int $opsJobItemChannelId, int $parentNeeded, array $childrenInput): array
    {
        $children = array_map(fn ($c) => [
            'key' => (int) $c['child_product_id'],
            'weight' => (int) $c['weight'],
            'available' => $c['available'] ?? true,
            'cap' => array_key_exists('cap', $c) ? $c['cap'] : null,
            'sort' => (int) ($c['sort'] ?? 0),
        ], $childrenInput);

        $alloc = self::allocateToPick(max(0, $parentNeeded), $children);

        DB::transaction(function () use ($opsJobItemChannelId, $childrenInput, $alloc) {
            $keep = [];
            foreach (array_values($childrenInput) as $i => $c) {
                $childId = (int) $c['child_product_id'];
                $row = OpsJobItemChannelChild::updateOrCreate(
                    [
                        'ops_job_item_channel_id' => $opsJobItemChannelId,
                        'child_product_id' => $childId,
                    ],
                    [
                        'weight_pct' => (int) $c['weight'],
                        'to_pick_qty' => $alloc[$childId] ?? 0,
                        'sort' => (int) ($c['sort'] ?? $i),
                    ]
                );
                $keep[] = $row->id;
            }

            OpsJobItemChannelChild::where('ops_job_item_channel_id', $opsJobItemChannelId)
                ->when(!empty($keep), fn ($q) => $q->whereNotIn('id', $keep))
                ->delete();
        });

        return $alloc;
    }

    /**
     * @param  array<int,int>  $parentIds
     * @return array{updated:int,incomplete:int,unchanged:int}
     */
    public static function recomputeParents(array $parentIds): array
    {
        $tally = ['updated' => 0, 'incomplete' => 0, 'unchanged' => 0];
        if (empty($parentIds)) {
            return $tally;
        }

        Product::withoutGlobalScopes()
            ->whereIn('id', $parentIds)
            ->where('is_parent_sku', true)
            ->with(['activeBlindChildren.childProduct.latestUnitCost'])
            ->chunkById(200, function ($parents) use (&$tally) {
                foreach ($parents as $parent) {
                    $tally[self::recomputeForParent($parent)]++;
                }
            });

        return $tally;
    }
}
