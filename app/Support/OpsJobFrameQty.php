<?php

namespace App\Support;

use App\Models\OpsJobItem;
use App\Models\OpsJobItemChannel;
use App\Models\Vend;

/**
 * Which entry of a B/A frame describes an ops-job channel row, and the qty it
 * carries. ONE rule, because it had three (2026-09-23).
 *
 * The rule: a SKU-stocked machine (Smart Freezer, Smart Chiller —
 * `Vend::isSkuStocked`) identifies a row by PRODUCT, so match on our
 * `products.id`; the code + suffix is only a position label, and two SKUs may
 * share one number as 101A / 101B. A vending machine identifies a row by the
 * board's slot code.
 *
 * **Gate on the MACHINE, never on "the payload has a product_id".** A vending
 * board's frame carries `product_id` too — its own slot index (1, 2, 21 …),
 * never a `products.id`. `SyncVendChannels` keyed on the field's presence and
 * so compared 1 against 506, matching nothing and leaving After Refill blank
 * for every item completed before its A frame landed (prod, 2026-09-23).
 *
 * Callers write on their own terms — the completion path and the A-frame path
 * with `update()`, the CityBox restock quietly (its observer must not re-fire),
 * the repair command only where the column is still null — so this class owns
 * the MATCH and leaves the write to `apply()`'s caller or to a hand-written
 * loop over `qtyFor()`.
 */
final class OpsJobFrameQty
{
    /** Does this frame entry describe this ops-job row? */
    public static function matches(array $entry, OpsJobItemChannel $opsRow, bool $skuStocked): bool
    {
        if ($skuStocked) {
            return ! empty($entry['product_id'])
                && (int) $entry['product_id'] === (int) $opsRow->product_id;
        }

        return isset($entry['channel_code'])
            && $entry['channel_code'] == $opsRow->vend_channel_code;
    }

    /**
     * The qty this row's entry carries, or null when the frame has none for it
     * (a channel the machine did not report — left alone, never zeroed).
     *
     * @param  array<string,mixed>|null  $frame  a B/A frame: `['channels' => [...]]`
     */
    public static function qtyFor(?array $frame, OpsJobItemChannel $opsRow, bool $skuStocked): ?int
    {
        foreach ($frame['channels'] ?? [] as $entry) {
            if (self::matches($entry, $opsRow, $skuStocked)) {
                return isset($entry['qty']) ? (int) $entry['qty'] : null;
            }
        }

        return null;
    }

    /**
     * Write one frame's qty onto every row of an item, returning how many rows
     * were filled. `$onlyWhenNull` is the repair command's mode: never overwrite
     * a value some other path already resolved.
     *
     * `$skuStocked` is derived from the item's machine unless a caller that
     * already holds the Vend passes it — SyncVendChannels runs on every frame,
     * so it must not pay for a lookup it can answer itself.
     *
     * @param  array<string,mixed>|null  $frame
     * @param  'vmc_before_qty'|'vmc_after_qty'  $column
     */
    public static function apply(OpsJobItem $item, ?array $frame, string $column, bool $onlyWhenNull = false, ?bool $skuStocked = null): int
    {
        if (! $frame) {
            return 0;
        }

        $skuStocked ??= self::isSkuStocked($item);
        $filled = 0;

        foreach ($item->opsJobItemChannels as $opsRow) {
            if ($onlyWhenNull && $opsRow->{$column} !== null) {
                continue;
            }

            $qty = self::qtyFor($frame, $opsRow, $skuStocked);

            if ($qty !== null) {
                $opsRow->update([$column => $qty]);
                $filled++;
            }
        }

        return $filled;
    }

    /**
     * Whether the item's machine keys stock by SKU. Falls back to the vending
     * rule when the vend is gone — a slot code is what an orphaned row holds.
     */
    public static function isSkuStocked(OpsJobItem $item): bool
    {
        $vend = $item->relationLoaded('vend') ? $item->vend : Vend::find($item->vend_id);

        return (bool) $vend?->isSkuStocked();
    }
}
