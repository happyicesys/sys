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
 *    A frame), a hand overwrite on Setting/Edit, and the very first read of a chiller. Each one
 *    records the moment their count was READ as the vend's baseline.
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

    /** When their count was last read into our ledger; null = never (the next read seeds it). */
    public static function baselineAt(Vend $vend): ?CarbonInterface
    {
        $at = ($vend->citybox_status_json ?? [])['stock_baseline_at'] ?? null;

        return $at ? Carbon::parse($at) : null;
    }

    /** Record that our ledger now holds their count as read at $readAt. Never moves backwards. */
    public static function markBaseline(Vend $vend, CarbonInterface $readAt, string $source): void
    {
        $current = self::baselineAt($vend);
        if ($current && $current->greaterThanOrEqualTo($readAt)) {
            return;
        }
        $json = $vend->citybox_status_json ?? [];
        $json['stock_baseline_at'] = $readAt->toDateTimeString();
        $json['stock_baseline_source'] = $source;
        $vend->forceFill(['citybox_status_json' => $json])->save();
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
        $baseline = self::baselineAt($vend);
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

        DB::transaction(function () use ($order, $vend, $moves, $target) {
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
        });

        return $moves !== [];
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
