<?php

namespace App\Services\Citybox;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\CityboxOrder;
use App\Models\Vend;
use App\Models\VendChannel;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A CityBox chiller's stock is OUR ledger (Brian, 2026-10-13), like a Smart Freezer's:
 *
 *  - **Absolute counts** set it: the driver's Stock In (pushed to CityBox, then read back as the
 *    A frame), an Undo Stock In's revert, and the very first read of a chiller. Each one records
 *    the moment their count was READ as `vends.stock_baseline_at` — in the same transaction that
 *    writes the count (SyncVendChannels → countLanded), never at dispatch. A hand overwrite on
 *    Setting/Edit sets one SKU, not a count, and records no baseline (ChannelQtyAdjuster).
 *  - **CityBox's own orders** move it in between: each order takes its units off once, per SKU.
 *    An order taken at or before the baseline is already inside that count and moves nothing.
 *  - **Their live count** (device_product) only CHECKS it now — StockPollService::checkLedger —
 *    and never overwrites it, so a deduction can never be lost to a poll that read their count
 *    a few seconds earlier.
 *
 * Exactly once: the units an order has taken are stored on it (`stock_deducted_json`) in the
 * same transaction that moves vend_channels, so a re-pull applies only the DIFFERENCE — an AI
 * correction, a cancel — and nothing at all for an order that did not change. A change that
 * arrives after a newer count is not applied: that count already holds the truth.
 *
 * A pay-later order is in `order_list` only once paid, so its goods leave our ledger then, not
 * at the door. The stock check is what shows that gap meanwhile.
 */
class ChillerStockLedger
{
    /** Order statuses whose goods have left the cabinet (a refund returns money, not goods). */
    private const GOODS_TAKEN = [
        \App\Services\Citybox\DTO\ChillerOrder::STATUS_UNPAID,
        \App\Services\Citybox\DTO\ChillerOrder::STATUS_PAID,
        \App\Services\Citybox\DTO\ChillerOrder::STATUS_PAYING,
        \App\Services\Citybox\DTO\ChillerOrder::STATUS_REFUND_APPLIED,
        \App\Services\Citybox\DTO\ChillerOrder::STATUS_REFUNDED,
    ];

    public static function enabled(): bool
    {
        return (bool) config('citybox.stock_ledger', true);
    }

    /**
     * When their count was last read into our ledger; null = never (the next read seeds it). Read
     * fresh from the row, never from a model that may have been loaded minutes ago.
     */
    public static function baselineAt(Vend $vend): ?CarbonInterface
    {
        return self::readBaseline($vend->id, lock: false);
    }

    /**
     * Record that our ledger now holds their count as read at $readAt. Never moves backwards: a
     * conditional UPDATE on its own column (audit STK-02), so no stale copy of the vend can put an
     * older baseline back. Returns false when a newer (or equal) baseline was already there.
     */
    public static function markBaseline(Vend $vend, CarbonInterface $readAt, string $source): bool
    {
        $at = $readAt->toDateTimeString();
        $won = DB::table('vends')->where('id', $vend->id)
            ->where(fn ($q) => $q->whereNull('stock_baseline_at')->orWhere('stock_baseline_at', '<', $at))
            ->update(['stock_baseline_at' => $at, 'stock_baseline_source' => mb_substr($source, 0, 32)]) > 0;
        if ($won) {
            // Keep the in-memory model in step without making it dirty.
            $vend->setRawAttributes(array_merge($vend->getAttributes(), ['stock_baseline_at' => $at, 'stock_baseline_source' => $source]), true);
        }

        return $won;
    }

    /**
     * An absolute count, read at $readAt, has just been written into vend_channels (SyncVendChannels,
     * inside its transaction, holding the vend row lock). Records the baseline and takes back off the
     * units of every order taken AFTER the read that the ledger had already deducted while the frame
     * waited on the queue: their count does not hold those, and the overwrite just put them back.
     */
    public static function countLanded(Vend $vend, CarbonInterface $readAt, string $source): void
    {
        if (! self::markBaseline($vend, $readAt, $source)) {
            return;
        }
        $after = CityboxOrder::where('vend_id', $vend->id)
            ->whereNotNull('stock_deducted_at')
            ->where('ordered_at', '>', $readAt->toDateTimeString())
            ->get(['id', 'stock_deducted_json']);
        foreach ($after as $order) {
            foreach ((array) ($order->stock_deducted_json ?? []) as $productId => $units) {
                if ((int) $units > 0) {
                    VendChannel::where('vend_id', $vend->id)->where('product_id', (int) $productId)->where('is_active', true)
                        ->update(['qty' => DB::raw('GREATEST(CAST(qty AS SIGNED) - '.(int) $units.', 0)')]);
                }
            }
        }
    }

    /** The baseline under the vend row lock: an absolute count landing and an order cannot interleave. */
    public static function lockedBaselineAt(int $vendId): ?CarbonInterface
    {
        return self::readBaseline($vendId, lock: true);
    }

    private static function readBaseline(int $vendId, bool $lock): ?CarbonInterface
    {
        $query = DB::table('vends')->where('id', $vendId);
        $row = ($lock ? $query->lockForUpdate() : $query)->first(['stock_baseline_at', 'citybox_status_json']);
        $at = $row?->stock_baseline_at;
        if ($at === null && $row?->citybox_status_json) {
            // Only for the seconds between the code and its backfill migration landing.
            $at = (json_decode((string) $row->citybox_status_json, true) ?: [])['stock_baseline_at'] ?? null;
        }

        return $at ? Carbon::parse($at) : null;
    }

    /**
     * Bring the ledger in line with this order's current lines. Returns true when vend_channels
     * moved. $productIds maps CityBox product id → mark1 product id.
     *
     * @param  array<int,int>  $productIds
     */
    public function apply(CityboxOrder $order, ?Vend $vend, array $productIds): bool
    {
        if (! $vend) {
            $this->note($order, 'unknown_device');

            return false;
        }
        if (! self::enabled()) {
            $this->note($order, 'ledger_off');

            return false;
        }

        return DB::transaction(function () use ($order, $vend, $productIds) {
            // The vend row lock serialises this with an absolute count landing (countLanded).
            $baseline = self::lockedBaselineAt($vend->id);
            if ($baseline === null) {
                return false; // the chiller's first read seeds the ledger; this order is decided after it
            }
            if ($order->stock_deducted_at === null && $order->ordered_at !== null && $order->ordered_at->lessThanOrEqualTo($baseline)) {
                $this->note($order, 'in_baseline_count');

                return false;
            }
            if ($order->stock_deducted_at !== null && $baseline->greaterThan($order->stock_deducted_at)) {
                // A count was taken after we applied this order: it already reflects whatever the order did.
                return false;
            }

            $target = in_array((int) $order->order_status, self::GOODS_TAKEN, true) ? $this->unitsByProduct($order, $productIds) : [];
            $done = array_map('intval', (array) ($order->stock_deducted_json ?? []));

            $moves = [];
            foreach (array_unique(array_merge(array_keys($target), array_keys($done))) as $productId) {
                $delta = ($target[$productId] ?? 0) - ($done[$productId] ?? 0);
                if ($delta !== 0) {
                    $moves[(int) $productId] = $delta;
                }
            }
            if ($moves === [] && $order->stock_deducted_at !== null) {
                return false;
            }

            foreach ($moves as $productId => $units) {
                // Positive = units out; negative = an order that shrank gives units back.
                VendChannel::where('vend_id', $vend->id)->where('product_id', $productId)->where('is_active', true)
                    ->update(['qty' => DB::raw('GREATEST(CAST(qty AS SIGNED) - '.(int) $units.', 0)')]);
            }
            $order->forceFill([
                'stock_deducted_json' => $target === [] ? [] : array_map('intval', $target),
                'stock_deducted_at' => now(),
                'stock_note' => null,
            ])->save();

            return $moves !== [];
        });
    }

    /** Dashboard JSON after the ledger moved; once per vend per sweep, after the writes commit. */
    public function refresh(int $vendId): void
    {
        SaveVendChannelsJson::dispatch($vendId)->onQueue('default');
    }

    /**
     * @param  array<int,int>  $productIds  CityBox id → mark1 product id
     * @return array<int,int> mark1 product id → units
     */
    private function unitsByProduct(CityboxOrder $order, array $productIds): array
    {
        $units = [];
        foreach ($order->items as $item) {
            $productId = $item->product_id ?: ($productIds[(int) $item->citybox_product_id] ?? null);
            if ($productId && $item->qty > 0) {
                $units[(int) $productId] = ($units[(int) $productId] ?? 0) + (int) $item->qty;
            }
        }

        return $units;
    }

    private function note(CityboxOrder $order, string $note): void
    {
        if ($order->stock_note !== $note) {
            $order->forceFill(['stock_note' => $note])->save();
        }
    }
}
