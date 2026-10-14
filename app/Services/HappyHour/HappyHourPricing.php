<?php

namespace App\Services\HappyHour;

use App\Models\HappyHourSlot;
use App\Models\Scopes\OperatorHappyHourCampaignScope;
use App\Models\Vend;
use Carbon\CarbonInterface;

/** Promo price arithmetic and "which price was live at this moment" — integer cents throughout. */
final class HappyHourPricing
{
    /**
     * The original price less the discount, rounded UP to the campaign's price step (Brian,
     * 2026-10-14: "round up 10 cents, ceiling instead of floor"): 30% off $2.50 = $1.75 → $1.80.
     * The screens still advertise the campaign's own % ("30% off"), whatever the rounding does.
     * Where rounding up would leave no discount at all (30% off 30¢ = 21¢ → 30¢), it rounds down
     * instead, so a machine in the campaign always gets a real deal. Null when no price between
     * 1¢ and the original exists.
     */
    public static function promoPrice(int $original, int $discountPct, int $stepCents): ?int
    {
        $step = max(1, $stepCents);
        $exact = $original * (100 - $discountPct); // hundredths of a cent, so no early truncation
        $unit = 100 * $step;
        $promo = intdiv($exact + $unit - 1, $unit) * $step;
        if ($promo >= $original) {
            $promo = intdiv($exact, $unit) * $step;
        }

        return $promo >= 1 && $promo < $original ? $promo : null;
    }

    /**
     * Whether this machine's app can show and charge Happy Hour prices: a freezer on app 30+ or a
     * big-board vending machine on app 315+ (HappyHourEligibility decides, in one place).
     */
    public static function supports(Vend $vend): bool
    {
        return HappyHourEligibility::supports($vend);
    }

    /**
     * Promo price per product for slots of this machine that covered $at — how an AI-judged extra
     * unit is valued when nobody paid a price for it.
     *
     * @param  list<int>  $productIds
     * @return array<int, int>
     */
    public static function promoPricesAt(int $vendId, array $productIds, CarbonInterface $at): array
    {
        if ($productIds === []) {
            return [];
        }

        return HappyHourSlot::query()
            ->where('vend_id', $vendId)
            ->whereIn('product_id', $productIds)
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at)
            ->pluck('promo_price', 'product_id')
            ->map(fn ($cents) => (int) $cents)
            ->all();
    }

    /**
     * The `happy_hour` field of each menu row: this machine's open slots that end within the menu
     * horizon, keyed by product. The freezer switches between them on its own clock.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public static function menuSlots(Vend $vend, CarbonInterface $now): array
    {
        if (! self::supports($vend)) {
            return [];
        }

        return HappyHourSlot::query()
            ->open()
            ->where('vend_id', $vend->id)
            ->where('ends_at', '>', $now)
            ->where('starts_at', '<', $now->copy()->addHours((int) config('happy_hour.menu_horizon_hours', 24)))
            ->orderBy('starts_at')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($slots) => $slots->map(fn (HappyHourSlot $s) => [
                'slot_id' => $s->id,
                'starts_at' => $s->starts_at->toIso8601String(),
                'ends_at' => $s->ends_at->toIso8601String(),
                'price' => $s->promo_price,
                'original_price' => $s->original_price,
                'discount_pct' => $s->discount_pct,
                // Its place in the live ranking when picked (1 = best): with several SKUs on offer at
                // once, the one a single-SKU display should feature.
                'rank' => $s->rank,
            ])->values()->all())
            ->all();
    }

    /**
     * A vending machine's not-yet-ended open slots within the menu horizon, by product, for
     * `/thumbnails` (one query; the campaign's price step comes along for the per-channel price).
     *
     * @return \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, HappyHourSlot>>
     */
    public static function openSlotsByProduct(Vend $vend, CarbonInterface $now)
    {
        return HappyHourSlot::query()
            ->open()
            ->with(['campaign' => fn ($q) => $q->withoutGlobalScope(OperatorHappyHourCampaignScope::class)->withTrashed()->select('id', 'price_step_cents')])
            ->where('vend_id', $vend->id)
            ->where('ends_at', '>', $now)
            ->where('starts_at', '<', $now->copy()->addHours((int) config('happy_hour.menu_horizon_hours', 24)))
            ->orderBy('starts_at')->orderBy('rank')
            ->get()
            ->groupBy('product_id');
    }

    /**
     * The `happy_hour` field of one `/thumbnails` row: the product's slots priced for THIS channel —
     * the slot's discount applied to the channel's own price with the campaign's rounding, so two
     * channels of one SKU at different board prices each get their own promo price. A slot whose
     * discount does not give a price between 1¢ and the channel price is left out for that row.
     *
     * @param  iterable<HappyHourSlot>  $slots
     * @return list<array<string, mixed>>
     */
    public static function channelSlots(iterable $slots, int $channelPrice): array
    {
        $rows = [];
        foreach ($slots as $slot) {
            $price = $channelPrice > 0
                ? self::promoPrice($channelPrice, (int) $slot->discount_pct, (int) ($slot->campaign?->price_step_cents ?? 1))
                : null;
            if ($price === null) {
                continue;
            }
            $rows[] = [
                'slot_id' => $slot->id,
                'starts_at' => $slot->starts_at->toIso8601String(),
                'ends_at' => $slot->ends_at->toIso8601String(),
                'price' => $price,
                'original_price' => $channelPrice,
                'discount_pct' => (int) $slot->discount_pct,
                'rank' => $slot->rank ?? 1,
            ];
        }

        return $rows;
    }
}
