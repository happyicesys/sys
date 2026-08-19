<?php

namespace App\Services\Citybox;

use App\Services\Citybox\DTO\ChannelFrame;
use App\Services\Citybox\DTO\ChillerStockLine;
use Illuminate\Support\Collection;

/**
 * Pure: (live stock lines, planogram code map) → ChannelFrame (design §3).
 *   qty      = live quantity
 *   capacity = par (their denominator, decided = our capacity)
 *   amount   = effective price cents; amount2 = list price cents
 *   error_code = 0 (chillers have no per-channel motor faults)
 * A product on the device but absent from the planogram gets no channel —
 * it will appear after the next planogram sync (hourly / on Pull).
 */
class ChannelFrameAdapter
{
    /**
     * @param  Collection<int,ChillerStockLine>  $stock
     * @param  array<int,array{code:int,par:int,layer:int}>  $codes
     */
    public function toFrame(Collection $stock, array $codes, ?string $label = null): ChannelFrame
    {
        $channels = [];
        foreach ($stock as $line) {
            $entry = $codes[$line->cityboxProductId] ?? null;
            if (! $entry) {
                continue;
            }
            $channels[] = [
                'channel_code' => $entry['code'],
                'qty' => $line->quantity,
                'capacity' => $entry['par'],
                'amount' => $line->effectivePriceCents() ?? 0,
                'amount2' => $line->priceCents ?? 0,
                'error_code' => 0,
            ];
        }
        usort($channels, fn ($a, $b) => $a['channel_code'] <=> $b['channel_code']);

        return new ChannelFrame($channels, $label);
    }
}
