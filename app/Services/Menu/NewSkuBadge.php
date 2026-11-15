<?php

namespace App\Services\Menu;

use App\Models\ProductMappingItem;
use App\Models\Vend;
use App\Models\VendProductArrival;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * The menu NEW badge (Brian, 2026-11-15): a SKU is NEW on a machine for 60 days after it
 * first appears in THAT machine's product mapping. New to the machine, not new to the company:
 * a machine that has carried the SKU for longer shows no badge, even on the same mapping.
 *
 * A SKU that left the machine and comes back within 60 days is not new; one that was gone
 * for longer arrives again.
 *
 * The apps (big board 328+, freezer 58+) get `new_until` (ISO-8601) on each menu row while
 * the SKU is new and draw the badge until that time; older apps ignore the key.
 *
 * Arrivals are recorded when a menu is served (so a board that re-reads its menu right after
 * a mapping change already sees the badge) and by the hourly `menu:track-new-skus` scan.
 */
final class NewSkuBadge
{
    public const DAYS = 60;

    /** arrived_at of a SKU that was on the machine before tracking began: never new. */
    public const BASELINE = '2000-01-01 00:00:00';

    /**
     * Records arrivals and departures for the machine's current mapping. Writes only when
     * something changed: a SKU first seen, back on the mapping, or gone from it.
     */
    public static function sync(Vend $vend, ?CarbonInterface $now = null): void
    {
        if (! $vend->product_mapping_id) {
            return;
        }
        $now = CarbonImmutable::instance($now ?? now());

        $productIds = ProductMappingItem::query()
            ->where('product_mapping_id', $vend->product_mapping_id)
            ->distinct()
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        // An emptied mapping still runs: every SKU on it has left.

        $existing = VendProductArrival::query()
            ->where('vend_id', $vend->id)
            ->get(['product_id', 'left_at'])
            ->keyBy('product_id');

        // A machine seen for the first time (installed or first mapped after this went live) takes
        // its mapping as the baseline: a badge on every tile would mean nothing.
        if ($existing->isEmpty()) {
            if ($productIds !== []) {
                VendProductArrival::query()->insertOrIgnore(array_map(fn ($productId) => [
                    'vend_id' => $vend->id,
                    'product_id' => $productId,
                    'arrived_at' => self::BASELINE,
                    'left_at' => null,
                    'source' => VendProductArrival::SOURCE_SEED,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $productIds));
            }

            return;
        }

        $onMapping = array_flip($productIds);
        $longGone = $now->subDays(self::DAYS);
        $missing = [];
        $returned = [];
        $backSoon = [];
        $left = [];

        foreach ($productIds as $productId) {
            $row = $existing->get($productId);
            if (! $row) {
                $missing[] = $productId;
            } elseif ($row->left_at !== null) {
                if ($row->left_at->lt($longGone)) {
                    $returned[] = $productId;
                } else {
                    $backSoon[] = $productId;
                }
            }
        }
        foreach ($existing as $productId => $row) {
            if ($row->left_at === null && ! isset($onMapping[(int) $productId])) {
                $left[] = (int) $productId;
            }
        }

        if ($missing !== []) {
            VendProductArrival::query()->insertOrIgnore(array_map(fn ($productId) => [
                'vend_id' => $vend->id,
                'product_id' => $productId,
                'arrived_at' => $now,
                'left_at' => null,
                'source' => VendProductArrival::SOURCE_MAPPING,
                'created_at' => $now,
                'updated_at' => $now,
            ], $missing));
        }
        if ($returned !== []) {
            self::rows($vend, $returned)->update([
                'arrived_at' => $now,
                'left_at' => null,
                'source' => VendProductArrival::SOURCE_RETURNED,
                'updated_at' => $now,
            ]);
        }
        if ($backSoon !== []) {
            self::rows($vend, $backSoon)->update(['left_at' => null, 'updated_at' => $now]);
        }
        if ($left !== []) {
            self::rows($vend, $left)->update(['left_at' => $now, 'updated_at' => $now]);
        }
    }

    /** @param  array<int, int>  $productIds */
    private static function rows(Vend $vend, array $productIds): \Illuminate\Database\Eloquent\Builder
    {
        return VendProductArrival::query()->where('vend_id', $vend->id)->whereIn('product_id', $productIds);
    }

    /**
     * product_id => the moment its badge ends, for the machine's SKUs that are NEW now.
     * Never throws: a menu must still be served if this fails (e.g. the deploy gap before the
     * table exists), it just shows no badges.
     *
     * @return array<int, CarbonImmutable>
     */
    public static function newUntilByProduct(Vend $vend, ?CarbonInterface $now = null): array
    {
        if (! $vend->product_mapping_id) {
            return [];
        }
        $now = CarbonImmutable::instance($now ?? now());

        try {
            self::sync($vend, $now);

            return VendProductArrival::query()
                ->where('vend_id', $vend->id)
                ->whereNull('left_at')
                ->where('arrived_at', '>', $now->subDays(self::DAYS))
                ->get(['product_id', 'arrived_at'])
                ->mapWithKeys(fn (VendProductArrival $row) => [
                    (int) $row->product_id => CarbonImmutable::instance($row->arrived_at)->addDays(self::DAYS),
                ])
                ->all();
        } catch (QueryException $e) {
            Log::warning('NewSkuBadge: no badges for vend '.$vend->id.': '.$e->getMessage());

            return [];
        }
    }

    /** The wire form the apps parse: ISO-8601 with offset, e.g. 2027-01-14T10:05:00+08:00. */
    public static function wire(CarbonImmutable $until): string
    {
        return $until->toIso8601String();
    }
}
