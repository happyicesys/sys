<?php

namespace App\Services\HappyHour;

use App\Models\HappyHourCampaign;
use App\Models\Product;
use App\Models\Vend;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Shortlists and ranks one freezer's SKUs for a campaign: which products are worth discounting now,
 * best first, and why each of the others is left out. Reads only — stock from `vend_channels`
 * (mark1's ledger; capacity = Real Capacity), recent sales from `vend_product_records` (daily, up to
 * yesterday), prices in the Site's tier exactly as the freezer menu does, unit cost from `unit_costs`.
 *
 * Ties are broken by the higher on-hand qty (Brian, 2026-10-09), then product id, so a ranking is
 * deterministic.
 */
class HappyHourRanker
{
    /** @return list<HappyHourCandidate> eligible first in rank order, then the excluded ones */
    public function rank(HappyHourCampaign $campaign, Vend $vend, CarbonInterface $now): array
    {
        $stock = DB::table('vend_channels')
            ->where('vend_id', $vend->id)
            ->where('is_active', 1)
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(GREATEST(qty, 0)) AS qty, SUM(GREATEST(capacity, 0)) AS capacity')
            ->get()
            ->keyBy('product_id');
        if ($stock->isEmpty()) {
            return [];
        }

        $productIds = $stock->keys()->map(fn ($id) => (int) $id)->all();
        $products = Product::query()->whereIn('id', $productIds)->get(['id', 'code', 'name'])->keyBy('id');
        $prices = $this->prices($vend, $productIds);
        $costs = $this->unitCosts($productIds);
        [$sold, $days] = $this->recentSales($vend, $productIds, $campaign->lookback_days, $now);
        $excluded = array_flip($campaign->excludedProductIds());

        $candidates = [];
        foreach ($productIds as $productId) {
            $row = $stock[$productId];
            $original = $prices[$productId] ?? null;
            $promo = $original ? HappyHourPricing::promoPrice($original, $campaign->discount_pct, $campaign->price_step_cents) : null;
            $candidate = new HappyHourCandidate(
                productId: $productId,
                productCode: $products[$productId]->code ?? null,
                productName: $products[$productId]->name ?? null,
                qty: (int) $row->qty,
                capacity: (int) $row->capacity > 0 ? (int) $row->capacity : null,
                soldInLookback: $sold[$productId] ?? 0,
                lookbackDays: $days,
                originalPrice: $original,
                promoPrice: $promo,
                unitCost: $costs[$productId] ?? null,
            );
            $candidate->excludedReason = $this->exclusion($campaign, $candidate, isset($excluded[$productId]));
            $candidates[] = $candidate;
        }

        $eligible = array_values(array_filter($candidates, fn (HappyHourCandidate $c) => $c->isEligible()));
        usort($eligible, $this->comparator($campaign, $vend, $now));
        foreach ($eligible as $i => $candidate) {
            $candidate->rank = $i + 1;
        }
        $rest = array_values(array_filter($candidates, fn (HappyHourCandidate $c) => ! $c->isEligible()));
        usort($rest, fn ($a, $b) => $b->qty <=> $a->qty ?: $a->productId <=> $b->productId);

        return [...$eligible, ...$rest];
    }

    /** @return list<HappyHourCandidate> the eligible ones only, best first */
    public function lineup(HappyHourCampaign $campaign, Vend $vend, CarbonInterface $now): array
    {
        return array_values(array_filter($this->rank($campaign, $vend, $now), fn ($c) => $c->isEligible()));
    }

    private function exclusion(HappyHourCampaign $campaign, HappyHourCandidate $c, bool $excludedByCampaign): ?string
    {
        $balance = $c->balancePct();

        return match (true) {
            $excludedByCampaign => 'Excluded in this campaign',
            $c->originalPrice === null || $c->originalPrice <= 0 => 'No selling price for this Site',
            $c->qty < max(1, $campaign->min_qty) => "Only {$c->qty} in stock (minimum {$campaign->min_qty})",
            $balance !== null && $balance <= $campaign->min_balance_pct => "Stock {$balance}% is at or under the {$campaign->min_balance_pct}% floor",
            $c->promoPrice === null => 'The discount does not change this price',
            ! $campaign->allow_below_cost && $c->unitCost !== null && $c->promoPrice < $c->unitCost => sprintf(
                'Promo $%s is below unit cost $%s', number_format($c->promoPrice / 100, 2), number_format($c->unitCost / 100, 2)
            ),
            default => null,
        };
    }

    private function comparator(HappyHourCampaign $campaign, Vend $vend, CarbonInterface $now): \Closure
    {
        $tieBreak = fn (HappyHourCandidate $a, HappyHourCandidate $b) => $b->qty <=> $a->qty ?: $a->productId <=> $b->productId;

        return match ($campaign->selection_rule) {
            HappyHourCampaign::RULE_BALANCE_PCT => fn ($a, $b) => ($b->balancePct() ?? -1) <=> ($a->balancePct() ?? -1) ?: $tieBreak($a, $b),
            HappyHourCampaign::RULE_RANDOM => function ($a, $b) use ($campaign, $vend, $now, $tieBreak) {
                $seed = "{$campaign->id}:{$vend->id}:{$now->toDateString()}";

                return crc32("{$seed}:{$a->productId}") <=> crc32("{$seed}:{$b->productId}") ?: $tieBreak($a, $b);
            },
            // Days of cover: nothing sold = lasts forever = first in line.
            default => fn ($a, $b) => ($b->daysOfCover() ?? INF) <=> ($a->daysOfCover() ?? INF) ?: $tieBreak($a, $b),
        };
    }

    /**
     * The menu's price for each product: the Site's selling-price tier (smart freezers always follow it).
     *
     * @param  list<int>  $productIds
     * @return array<int, int>
     */
    private function prices(Vend $vend, array $productIds): array
    {
        $type = $vend->serverPriceType();
        if ($type === null) {
            return [];
        }

        return DB::table('selling_prices')
            ->where('type', $type)
            ->whereIn('product_id', $productIds)
            ->pluck('amount', 'product_id')
            ->map(fn ($cents) => (int) $cents)
            ->all();
    }

    /**
     * Current unit cost in cents (the general row, not a blended per-mapping one).
     *
     * @param  list<int>  $productIds
     * @return array<int, int>
     */
    private function unitCosts(array $productIds): array
    {
        return DB::table('unit_costs')
            ->whereIn('product_id', $productIds)
            ->where('is_current', 1)
            ->whereNull('product_mapping_id')
            ->orderBy('date_from')
            ->pluck('cost', 'product_id')
            ->map(fn ($cents) => (int) $cents)
            ->all();
    }

    /**
     * Units sold per product over the lookback (up to yesterday — the daily records are built after
     * midnight), and the number of days that average spans: the lookback, or fewer when the machine's
     * records start later (a new freezer is not judged on days it did not exist).
     *
     * @param  list<int>  $productIds
     * @return array{0: array<int, int>, 1: int}
     */
    private function recentSales(Vend $vend, array $productIds, int $lookbackDays, CarbonInterface $now): array
    {
        $lookback = max(1, $lookbackDays);
        $to = $now->copy()->subDay()->toDateString();
        $from = $now->copy()->subDays($lookback)->toDateString();

        $sold = DB::table('vend_product_records')
            ->where('vend_id', $vend->id)
            ->whereIn('product_id', $productIds)
            ->whereBetween('date', [$from, $to])
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(total_count) AS units')
            ->pluck('units', 'product_id')
            ->map(fn ($units) => (int) $units)
            ->all();

        $first = DB::table('vend_product_records')->where('vend_id', $vend->id)->min('date');
        $days = $lookback;
        if ($first !== null && $first > $from) {
            $days = max(1, (int) $now->copy()->startOfDay()->diffInDays(\Carbon\Carbon::parse($first)->startOfDay(), true));
        }

        return [$sold, min($days, $lookback)];
    }
}
