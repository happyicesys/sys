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
 * The menu NEW badge (Brian, 2026-11-15): a SKU is NEW on a machine once it appears in THAT
 * machine's product mapping after launch — new to the machine, not new to the company. It has
 * NO expiry (Brian, 2026-11-16): it stays NEW until it leaves that machine's mapping.
 *
 * A SKU that left the machine and comes back within RETURN_AFTER_DAYS keeps what it had (old
 * stays old, new stays new); one that was gone for longer arrives again as NEW.
 *
 * The apps (big board 328+, freezer 58+) get `new_until` (ISO-8601) on each menu row while
 * the SKU is new and draw the badge until that time; with no expiry it is always NO_EXPIRY.
 * Older apps ignore the key.
 *
 * Arrivals are recorded when a menu is served (so a board that re-reads its menu right after
 * a mapping change already sees the badge) and by the hourly `menu:track-new-skus` scan.
 */
final class NewSkuBadge
{
    /** A SKU away from the machine for longer than this arrives again as NEW. */
    public const RETURN_AFTER_DAYS = 60;

    /** `new_until` while a SKU is new: the apps draw until this moment, so it is far away. */
    public const NO_EXPIRY = '2099-12-31 23:59:59';

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
        $longGone = $now->subDays(self::RETURN_AFTER_DAYS);
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
     * product_id => the moment its badge ends (always NO_EXPIRY), for the machine's SKUs that
     * are NEW now: arrived after launch (not a seed row) and still on its mapping.
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

            $until = CarbonImmutable::parse(self::NO_EXPIRY);

            return VendProductArrival::query()
                ->where('vend_id', $vend->id)
                ->whereNull('left_at')
                ->where('source', '!=', VendProductArrival::SOURCE_SEED)
                ->pluck('product_id')
                ->mapWithKeys(fn ($productId) => [(int) $productId => $until])
                ->all();
        } catch (QueryException $e) {
            Log::warning('NewSkuBadge: no badges for vend '.$vend->id.': '.$e->getMessage());

            return [];
        }
    }

    /** The wire form the apps parse: ISO-8601 with offset, e.g. 2099-12-31T23:59:59+08:00. */
    public static function wire(CarbonImmutable $until): string
    {
        return $until->toIso8601String();
    }
}
