<?php

namespace App\Services\HappyHour;

use App\Models\HappyHourCampaign;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\Product;
use App\Models\Vend;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Shortlists and ranks one freezer's SKUs for a campaign: which products are worth discounting now,
 * best first, and why each of the others is left out. Reads only — stock from `vend_channels`
 * (mark1's ledger; capacity = Real Capacity), recent sales from `vend_product_records` (daily, up to
 * yesterday), unit cost from `unit_costs`. Price: a freezer's is the Site's tier exactly as its menu
 * shows it; a vending machine's is what its screen shows — the VMC board price
 * (`vend_channels.amount`), or the Site tier where the machine follows server price (`/thumbnails`
 * `server_price`, which the APK prefers when set).
 *
 * Ties are broken by the higher on-hand qty (Brian, 2026-10-09), then product id, so a ranking is
 * deterministic.
 *
 * Bestseller hold (Brian, 2026-10-16): for `bestseller_hold_hours` after the machine's last Stock In, its
 * top `bestseller_hold_count` sellers of the last `bestseller_lookback_days` are left out (bestsellerHold).
 *
 * Prioritize (Brian, 2026-10-16: to clear stock, or when a supplier funds the discount): a SKU on the
 * campaign's `priority_product_ids` is marked `priority` and ranks ahead of the rest, so the planner can offer
 * it in every other slot. It keeps the campaign's discount %, rounding and below-cost rule and needs a price,
 * but only 1 unit in stock: the stock floor %, the minimum units, the warehouse-availability box and the
 * bestseller hold do not apply to it (clearing stock means selling the last units of a SKU on its way out).
 * "Never discount" still wins; the page refuses a SKU on both lists.
 */
class HappyHourRanker
{
    /** Why a SKU is left out, one key per rule of exclusion() — the campaign page groups the skipped SKUs on it. */
    public const EXCLUDED_CAMPAIGN = 'campaign';

    public const EXCLUDED_UNAVAILABLE = 'unavailable';

    public const EXCLUDED_BESTSELLER = 'bestseller';

    public const EXCLUDED_NO_PRICE = 'no_price';

    public const EXCLUDED_PRIORITY_EMPTY = 'priority_empty';

    public const EXCLUDED_MIN_QTY = 'min_qty';

    public const EXCLUDED_FLOOR = 'floor';

    public const EXCLUDED_NO_DISCOUNT = 'no_discount';

    public const EXCLUDED_BELOW_COST = 'below_cost';

    /** @return list<HappyHourCandidate> eligible first in rank order, then the excluded ones */
    public function rank(HappyHourCampaign $campaign, Vend $vend, CarbonInterface $now): array
    {
        $stock = DB::table('vend_channels')
            ->where('vend_id', $vend->id)
            ->where('is_active', 1)
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            // board_price: the highest priced channel of the SKU (a vending machine may hold one SKU in
            // two channels at different board prices; the slot records the highest, and each channel's
            // own promo price is worked out from its own price on `/thumbnails`).
            ->selectRaw('product_id, SUM(GREATEST(qty, 0)) AS qty, SUM(GREATEST(capacity, 0)) AS capacity, MAX(CASE WHEN amount > 0 THEN amount END) AS board_price')
            ->get()
            ->keyBy('product_id');
        if ($stock->isEmpty()) {
            return [];
        }

        $productIds = $stock->keys()->map(fn ($id) => (int) $id)->all();
        $products = Product::query()->whereIn('id', $productIds)->get(['id', 'code', 'name', 'is_available'])->keyBy('id');
        $prices = $vend->machine_type === Vend::MACHINE_TYPE_VENDING_MACHINE
            ? $this->vendingPrices($vend, $productIds, $stock)
            : $this->prices($vend, $productIds);
        $costs = $this->unitCosts($productIds);
        [$sold, $days] = $this->recentSales($vend, $productIds, $campaign->lookback_days, $now);
        $excluded = array_flip($campaign->excludedProductIds());
        $priority = array_diff_key(array_flip($campaign->priorityProductIds()), $excluded);
        // Prioritized SKUs are never held, so they do not take one of the hold's top places either.
        $held = $this->bestsellerHold($campaign, $vend, array_values(array_filter($productIds, fn ($id) => ! isset($priority[$id]))), $stock, $now);

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
            $candidate->priority = isset($priority[$productId]);
            // The warehouse's Available? tick (Product Movement) off = not offered while the campaign skips those.
            $unavailable = ! $candidate->priority && $campaign->skip_unavailable && isset($products[$productId]) && ! $products[$productId]->is_available;
            $hold = $held[$productId] ?? null;
            [$candidate->excludedCode, $candidate->excludedReason] = $this->exclusion($campaign, $candidate, isset($excluded[$productId]), $unavailable, $hold['reason'] ?? null);
            if ($candidate->excludedCode === self::EXCLUDED_BESTSELLER) {
                [$candidate->heldUntil, $candidate->holdPlace, $candidate->holdSold] = [$hold['until'], $hold['place'], $hold['sold']];
            }
            $candidates[] = $candidate;
        }

        $eligible = array_values(array_filter($candidates, fn (HappyHourCandidate $c) => $c->isEligible()));
        $comparator = $this->comparator($campaign, $vend, $now);
        usort($eligible, fn (HappyHourCandidate $a, HappyHourCandidate $b) => $b->priority <=> $a->priority ?: $comparator($a, $b));
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

    /** @return array{0: ?string, 1: ?string} [EXCLUDED_* key, the reason in words]; [null, null] = eligible */
    private function exclusion(HappyHourCampaign $campaign, HappyHourCandidate $c, bool $excludedByCampaign, bool $unavailable = false, ?string $held = null): array
    {
        $balance = $c->balancePct();

        return match (true) {
            $excludedByCampaign => [self::EXCLUDED_CAMPAIGN, 'Excluded in this campaign'],
            $unavailable => [self::EXCLUDED_UNAVAILABLE, 'Not available in the warehouse'],
            $held !== null => [self::EXCLUDED_BESTSELLER, $held],
            $c->originalPrice === null || $c->originalPrice <= 0 => [self::EXCLUDED_NO_PRICE, 'No selling price for this Site'],
            $c->priority && $c->qty < 1 => [self::EXCLUDED_PRIORITY_EMPTY, 'Prioritized, but out of stock'],
            ! $c->priority && $c->qty < max(1, $campaign->min_qty) => [self::EXCLUDED_MIN_QTY, "Only {$c->qty} in stock (minimum {$campaign->min_qty})"],
            ! $c->priority && $balance !== null && $balance <= $campaign->min_balance_pct => [self::EXCLUDED_FLOOR, "Stock {$balance}% is at or under the {$campaign->min_balance_pct}% floor"],
            $c->promoPrice === null => [self::EXCLUDED_NO_DISCOUNT, 'The discount does not change this price'],
            ! $campaign->allow_below_cost && $c->unitCost !== null && $c->promoPrice < $c->unitCost => [self::EXCLUDED_BELOW_COST, sprintf(
                'Promo $%s is below unit cost $%s', number_format($c->promoPrice / 100, 2), number_format($c->unitCost / 100, 2)
            )],
            default => [null, null],
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
     * The bestseller hold (Brian, 2026-10-16: "after stock in within X hours, exclude last 7 days top Y
     * bestseller SKU from happy hour"). A Stock In fills every SKU, so balance % would hand the first
     * slots after a driver visit to the fast sellers, which need no discount. For `bestseller_hold_hours`
     * after the machine's last Stock In, its top `bestseller_hold_count` SKUs by units sold over the last
     * `bestseller_lookback_days` (up to yesterday, the daily records; every sale counts, Happy Hour ones
     * too) are left out; then they rank again like any SKU. Off when either number is 0.
     *
     * Only SKUs the machine holds now compete (a SKU taken off the planogram would waste a place), and
     * only ones that sold at least one unit. A tie at the cut goes to the higher on-hand qty, then
     * product id, like the ranking itself.
     *
     * @param  list<int>  $productIds
     * @param  \Illuminate\Support\Collection<int|string, object>  $stock  the grouped vend_channels rows
     * @return array<int, array{reason: string, until: \Carbon\Carbon, place: int, sold: int}> product id => why it is held
     */
    private function bestsellerHold(HappyHourCampaign $campaign, Vend $vend, array $productIds, $stock, CarbonInterface $now): array
    {
        $hours = (int) $campaign->bestseller_hold_hours;
        $count = (int) $campaign->bestseller_hold_count;
        if ($hours < 1 || $count < 1) {
            return [];
        }
        $stockedInAt = $this->lastStockIn($vend, $now, $hours);
        if ($stockedInAt === null) {
            return [];
        }
        $until = $stockedInAt->copy()->addHours($hours);

        $days = in_array((int) $campaign->bestseller_lookback_days, HappyHourCampaign::BESTSELLER_LOOKBACK_DAYS, true)
            ? (int) $campaign->bestseller_lookback_days
            : HappyHourCampaign::BESTSELLER_LOOKBACK_DAYS[0];
        [$sold] = $this->recentSales($vend, $productIds, $days, $now);
        $sellers = array_keys(array_filter($sold, fn (int $units) => $units > 0));
        usort($sellers, fn (int $a, int $b) => $sold[$b] <=> $sold[$a]
            ?: (int) $stock[$b]->qty <=> (int) $stock[$a]->qty
            ?: $a <=> $b);

        $held = [];
        foreach (array_slice($sellers, 0, $count) as $i => $productId) {
            $held[$productId] = [
                'reason' => sprintf(
                    'Top %d seller (#%d, %d sold in %d days): not discounted until %s, %d h after the Stock In',
                    $count, $i + 1, $sold[$productId], $days, $until->format('d M H:i'), $hours,
                ),
                'until' => $until,
                'place' => $i + 1,
                'sold' => $sold[$productId],
            ];
        }

        return $held;
    }

    /**
     * When the machine was last stocked in within the hold: the newest ops job item at it with Stock In
     * done (`completed_at`; Undo Stock In clears it, so the hold lifts with it). A cancelled stop, or one
     * that brings no goods (return / on-site adjustment / melted stock), is not a Stock In.
     */
    private function lastStockIn(Vend $vend, CarbonInterface $now, int $hours): ?\Carbon\Carbon
    {
        $at = DB::table('ops_job_items')
            ->where('vend_id', $vend->id)
            ->where('completed_at', '>', $now->copy()->subHours($hours))
            ->where('completed_at', '<=', $now)
            ->where('status', '!=', OpsJob::STATUS_CANCELLED)
            ->where(fn ($q) => $q->whereNull('stock_action_type')->orWhereNotIn('stock_action_type', OpsJobItem::AUTO_PICK_STOCK_ACTIONS))
            ->max('completed_at');

        return $at === null ? null : \Carbon\Carbon::parse($at);
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
     * A vending machine's price per product, as its screen shows it: the Site tier where the machine
     * follows server price and the product has one (the APK prefers `server_price` when it is > 0),
     * else the board price of its channels — 287 of 294 touchscreen machines sell at the board price
     * (2026-10-12), and the tier alone would leave every one of their SKUs without a price.
     *
     * @param  list<int>  $productIds
     * @param  \Illuminate\Support\Collection<int|string, object>  $stock  the grouped vend_channels rows
     * @return array<int, int>
     */
    private function vendingPrices(Vend $vend, array $productIds, $stock): array
    {
        $prices = [];
        foreach ($productIds as $productId) {
            $board = (int) ($stock[$productId]->board_price ?? 0);
            if ($board > 0) {
                $prices[$productId] = $board;
            }
        }
        foreach ($this->prices($vend, $productIds) as $productId => $cents) {
            if ($cents > 0) {
                $prices[$productId] = $cents;
            }
        }

        return $prices;
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
