<?php

namespace App\Services\Citybox;

use App\Services\Citybox\DTO\ChannelFrame;
use App\Services\Citybox\DTO\ChillerSlot;
use App\Services\Citybox\DTO\ChillerStockLine;
use Illuminate\Support\Collection;

/**
 * Pure: (live stock lines, our channel slots) → ChannelFrame (design §3).
 *
 * OUR MAPPING decides which channels exist (`ChillerChannelMap::forVend`) and
 * what each one holds; the live-stock call (device_product) only supplies qty
 * and current price, per product. Before 2026-09-21 the channels came from
 * CityBox's Pre-Stock Setup instead, which meant a template switch in their
 * portal could empty a machine's planogram here (prod C5001).
 *
 *   channel  = every code in the vend's mapping
 *   qty      = live quantity, spread over the codes carrying that SKU
 *              (ChillerChannelMap::allocate); 0 when the live call omits it
 *   capacity = the SKU's chiller_slot_qty (ours — their par is not writable
 *              and caps nothing)
 *   amount   = live effective price, else their config's / the catalogue's last
 *              price — the live call omits sold-out products, and a sold-out
 *              channel priced 0 zeroes stock value and refill amounts
 *              (prod 2026-09-21: every channel on C6003)
 *   amount2  = live list price, same fallback
 *   error_code = 0 (chillers have no per-channel motor faults)
 * A SKU in the cabinet that our mapping does not carry gets no channel; the
 * overview lists it as off-planogram.
 */
class ChannelFrameAdapter
{
    /**
     * @param  Collection<int,ChillerStockLine>  $stock
     * @param  array<int,ChillerSlot>  $slots
     * @param  array<int,array{price:int,active:int}>  $fallbackPrices  by CityBox product id, cents —
     *                                                                  their live call OMITS a product the machine holds none of, and a
     *                                                                  sold-out channel must keep its price (stock value, refill amounts)
     */
    public function toFrame(Collection $stock, array $slots, ?string $label = null, array $fallbackPrices = []): ChannelFrame
    {
        $live = $stock->keyBy(fn (ChillerStockLine $l) => (int) $l->cityboxProductId);
        $qtyByCode = ChillerChannelMap::allocate(
            $slots,
            $live->map(fn (ChillerStockLine $l) => (int) $l->quantity)->all(),
        );

        $channels = [];
        foreach ($slots as $code => $slot) {
            /** @var ChillerStockLine|null $line */
            $line = $live->get($slot->cityboxProductId);
            $channels[] = [
                'channel_code' => $slot->code,
                'qty' => $qtyByCode[$slot->code] ?? 0,
                'capacity' => $slot->capacity,
                'amount' => $line?->effectivePriceCents() ?? ($fallbackPrices[$slot->cityboxProductId]['active'] ?? 0),
                'amount2' => $line?->priceCents ?? ($fallbackPrices[$slot->cityboxProductId]['price'] ?? 0),
                'error_code' => 0,
            ];
        }
        usort($channels, fn ($a, $b) => $a['channel_code'] <=> $b['channel_code']);

        return new ChannelFrame($channels, $label);
    }
}
