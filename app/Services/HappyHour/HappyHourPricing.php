<?php

namespace App\Services\HappyHour;

use App\Models\HappyHourSlot;
use App\Models\Vend;
use Carbon\CarbonInterface;

/** Promo price arithmetic and "which price was live at this moment" — integer cents throughout. */
final class HappyHourPricing
{
    /**
     * The original price less the discount, rounded DOWN to the campaign's price step (10¢ = $2.40,
     * not $2.43). Null when the discount does not produce a price between 1¢ and the original.
     */
    public static function promoPrice(int $original, int $discountPct, int $stepCents): ?int
    {
        $step = max(1, $stepCents);
        $promo = intdiv(intdiv($original * (100 - $discountPct), 100), $step) * $step;

        return $promo >= 1 && $promo < $original ? $promo : null;
    }

    /** Whether this machine's app can show and charge Happy Hour prices. */
    public static function supports(Vend $vend): bool
    {
        return $vend->machine_type === Vend::MACHINE_TYPE_SMART_FREEZER
            && (bool) $vend->is_active
            && (int) $vend->apk_version_code >= (int) config('happy_hour.min_freezer_apk_version', 30);
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
            ])->values()->all())
            ->all();
    }
}
