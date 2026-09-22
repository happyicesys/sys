<?php

namespace App\Services\Citybox;

use App\Services\Citybox\DTO\ChannelFrame;
use App\Services\Citybox\DTO\ChillerSlot;
use App\Services\Citybox\DTO\ChillerStockLine;
use Illuminate\Support\Collection;

/**
 * Pure: (live stock lines, our SKU slots) → ChannelFrame (design §3).
 *
 * OUR MAPPING decides which SKUs exist (`ChillerChannelMap::forVend`) and
 * where each one sits; the live-stock call (device_product) supplies qty and
 * current price, per product — the same unit as a row since 2026-09-22.
 *
 *   one entry per SKU, carrying product_id (the row's identity)
 *   channel_code + suffix = the SKU's position label, relabelled every sync
 *   qty      = the SKU's live quantity; 0 when the live call omits it
 *   capacity = ours (mapping override ?? chiller_slot_qty; their par caps nothing)
 *   amount   = live effective price, else their config's / the catalogue's last
 *              price — the live call omits sold-out products, and a sold-out
 *              row priced 0 zeroes stock value and refill amounts
 *              (prod 2026-09-21: every channel on C6003)
 *   amount2  = live list price, same fallback
 *   error_code = 0 (chillers have no per-channel motor faults)
 * A SKU in the cabinet that our mapping does not carry gets no row; the
 * overview lists it as off-planogram.
 */
class ChannelFrameAdapter
{
    /**
     * @param  Collection<int,ChillerStockLine>  $stock
     * @param  array<int,ChillerSlot>  $slots  keyed by product id
     * @param  array<int,array{price:int,active:int}>  $fallbackPrices  by CityBox product id, cents
     */
    public function toFrame(Collection $stock, array $slots, ?string $label = null, array $fallbackPrices = []): ChannelFrame
    {
        $live = $stock->keyBy(fn (ChillerStockLine $l) => (int) $l->cityboxProductId);

        $channels = [];
        foreach ($slots as $slot) {
            /** @var ChillerStockLine|null $line */
            $line = $live->get($slot->cityboxProductId);
            $channels[] = [
                'channel_code' => $slot->code,
                'suffix' => $slot->suffix,
                'product_id' => $slot->productId,
                'qty' => max(0, (int) ($line?->quantity ?? 0)),
                'capacity' => $slot->capacity,
                'amount' => $line?->effectivePriceCents() ?? ($fallbackPrices[$slot->cityboxProductId]['active'] ?? 0),
                'amount2' => $line?->priceCents ?? ($fallbackPrices[$slot->cityboxProductId]['price'] ?? 0),
                'error_code' => 0,
            ];
        }
        usort($channels, fn ($a, $b) => [$a['channel_code'], $a['suffix'] ?? ''] <=> [$b['channel_code'], $b['suffix'] ?? '']);

        return new ChannelFrame($channels, $label);
    }
}
