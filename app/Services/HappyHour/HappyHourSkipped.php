<?php

namespace App\Services\HappyHour;

use App\Models\HappyHourCampaign;
use App\Models\Vend;
use Carbon\CarbonInterface;

/**
 * The SKUs each machine is NOT offering in Happy Hour, and why (Brian, 2026-10-17: "show if there is any sku being
 * skipped for HH, especially the top 3 sellers L48h after stock in — a better picture of how the HH is being run").
 *
 * Ranked LIVE (HappyHourRanker at this moment, the same ranking the next slot will be picked from), not the reasons
 * recorded when the running slot was picked — so a SKU that just dropped under the floor shows here at once. A SKU
 * the machine is offering right now is left out of its list: a live slot keeps its SKU whatever the ranking says.
 * Bestseller holds come first (by their place in the top Y), then the other reasons, the campaign's own "never
 * discount" list last.
 */
class HappyHourSkipped
{
    /** Display order of the reasons; anything not named sorts after these. */
    private const ORDER = [
        HappyHourRanker::EXCLUDED_BESTSELLER,
        HappyHourRanker::EXCLUDED_PRIORITY_EMPTY,
        HappyHourRanker::EXCLUDED_MIN_QTY,
        HappyHourRanker::EXCLUDED_FLOOR,
        HappyHourRanker::EXCLUDED_UNAVAILABLE,
        HappyHourRanker::EXCLUDED_NO_PRICE,
        HappyHourRanker::EXCLUDED_NO_DISCOUNT,
        HappyHourRanker::EXCLUDED_BELOW_COST,
        HappyHourRanker::EXCLUDED_CAMPAIGN,
    ];

    public function __construct(private readonly HappyHourRanker $ranker) {}

    /**
     * @param  iterable<Vend>  $vends
     * @param  array<int, list<int>>  $onOffer  vend id => product ids on offer right now
     * @return array<int, list<array<string, mixed>>> vend id => its skipped SKUs
     */
    public function forMachines(HappyHourCampaign $campaign, iterable $vends, array $onOffer, CarbonInterface $now): array
    {
        $out = [];
        foreach ($vends as $vend) {
            $offered = array_flip($onOffer[$vend->id] ?? []);
            $skipped = array_values(array_filter(
                $this->ranker->rank($campaign, $vend, $now),
                fn (HappyHourCandidate $c) => ! $c->isEligible() && ! isset($offered[$c->productId]),
            ));
            usort($skipped, fn (HappyHourCandidate $a, HappyHourCandidate $b) => $this->order($a) <=> $this->order($b)
                ?: ($a->holdPlace ?? 0) <=> ($b->holdPlace ?? 0)
                ?: $b->qty <=> $a->qty
                ?: $a->productId <=> $b->productId);
            $out[$vend->id] = array_map(fn (HappyHourCandidate $c) => [
                'product_id' => $c->productId,
                'product' => $c->productName ?? "#{$c->productId}",
                'product_code' => $c->productCode,
                'code' => $c->excludedCode,
                'reason' => $c->excludedReason,
                'qty' => $c->qty,
                'capacity' => $c->capacity,
                'held_until' => $c->heldUntil?->toIso8601String(),
                'hold_place' => $c->holdPlace,
                'hold_sold' => $c->holdSold,
            ], $skipped);
        }

        return $out;
    }

    private function order(HappyHourCandidate $c): int
    {
        $i = array_search($c->excludedCode, self::ORDER, true);

        return $i === false ? count(self::ORDER) : $i;
    }
}
