<?php

namespace App\Services\Citybox;

use App\Services\Citybox\DTO\ChannelFrame;
use App\Services\Citybox\DTO\ChillerStockLine;
use Illuminate\Support\Collection;

/**
 * Pure: (live stock lines, planogram code map) → ChannelFrame (design §3).
 *
 * The PLANOGRAM (shipping_product = their Pre-Stock Setup) decides which
 * channels exist; the live-stock call (device_product) only supplies qty and
 * current price. This matters because their device_product omits products the
 * machine has never held: prod 2026-09-03, unit 10002 — par config lists 7 SKUs
 * (3 drinks + 4 snacks), device_product returned the 3 drinks on every one of
 * 2,178 polls, so the 4 snacks never got a vend_channels row and never reached
 * an ops job. Now:
 *   channel  = every product in the planogram (top-down, as the root CLAUDE.md
 *              says channel config must flow for the China projects)
 *   qty      = live quantity, 0 when the live call does not mention the product
 *   capacity = par (their denominator, decided = our capacity)
 *   amount   = live effective price, else the par config's price (cents)
 *   amount2  = live list price, else the par config's list price
 *   error_code = 0 (chillers have no per-channel motor faults)
 * A product on the device but absent from the planogram still gets no channel —
 * it appears after the next planogram sync (hourly / on Pull / on unknown SKU).
 */
class ChannelFrameAdapter
{
    /**
     * @param  Collection<int,ChillerStockLine>  $stock
     * @param  array<int,array{code:int,par:int,layer:int,price?:int,active?:int}>  $codes
     */
    public function toFrame(Collection $stock, array $codes, ?string $label = null): ChannelFrame
    {
        $live = $stock->keyBy(fn (ChillerStockLine $l) => $l->cityboxProductId);

        $channels = [];
        foreach ($codes as $cityboxProductId => $entry) {
            /** @var ChillerStockLine|null $line */
            $line = $live->get($cityboxProductId);
            $channels[] = [
                'channel_code' => $entry['code'],
                'qty' => $line?->quantity ?? 0,
                'capacity' => $entry['par'],
                'amount' => $line?->effectivePriceCents() ?? ($entry['active'] ?? $entry['price'] ?? 0),
                'amount2' => $line?->priceCents ?? ($entry['price'] ?? 0),
                'error_code' => 0,
            ];
        }
        usort($channels, fn ($a, $b) => $a['channel_code'] <=> $b['channel_code']);

        return new ChannelFrame($channels, $label);
    }
}
