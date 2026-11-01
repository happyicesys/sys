<?php

namespace App\Services\Grab\Menu;

use App\Models\GrabMenuProduct;
use App\Models\GrabMenuSetting;
use Illuminate\Support\Facades\DB;

/**
 * The Grab price of each product under one operator's rule, in integer cents. A product
 * with no price under the rule (no RP on the tier, no custom price) has none and is not
 * sold on Grab — never at $0.
 */
class GrabPriceList
{
    // Why a product has no Grab price.
    const NO_RP = 'no_rp';

    const NO_CUSTOM_PRICE = 'no_custom_price';

    /** @var array<int, int> product id => RP cents on the rule's tier */
    private array $rp;

    /** @var array<int, int> product id => custom cents */
    private array $custom;

    /** @param  int[]  $productIds */
    public function __construct(private GrabMenuSetting $setting, array $productIds)
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $this->rp = $setting->pricing_mode->readsRp() ? self::rpPrices($productIds, $setting->rp_type) : [];
        $this->custom = $setting->pricing_mode === GrabPricingMode::Custom
            ? GrabMenuProduct::query()
                ->where('operator_id', $setting->operator_id)
                ->whereIn('product_id', $productIds)
                ->whereNotNull('custom_amount')
                ->pluck('custom_amount', 'product_id')
                ->map(fn ($cents) => (int) $cents)
                ->all()
            : [];
    }

    public function price(int $productId): ?int
    {
        return match ($this->setting->pricing_mode) {
            GrabPricingMode::Rp => $this->rp[$productId] ?? null,
            GrabPricingMode::RpMultiplier => isset($this->rp[$productId])
                ? self::multiply($this->rp[$productId], $this->setting->multiplier_bp, $this->setting->round_up_cents)
                : null,
            GrabPricingMode::Custom => $this->custom[$productId] ?? null,
        };
    }

    // The RP on the rule's tier, shown beside the Grab price as "normal".
    public function rp(int $productId): ?int
    {
        return $this->rp[$productId] ?? null;
    }

    public function missingReason(int $productId): ?string
    {
        if ($this->price($productId) !== null) {
            return null;
        }

        return $this->setting->pricing_mode === GrabPricingMode::Custom ? self::NO_CUSTOM_PRICE : self::NO_RP;
    }

    /**
     * cents × multiplier, rounded UP to the step, in integers: 275 × 1.1 to 10¢ → 310.
     * $multiplierBp is basis points (×1.10 = 11000).
     */
    public static function multiply(int $cents, int $multiplierBp, int $stepCents): int
    {
        $step = max(1, $stepCents);
        $divisor = $step * 10000;

        return intdiv($cents * $multiplierBp + $divisor - 1, $divisor) * $step;
    }

    /**
     * Each product's RP on one tier, in cents; the newest row wins if a tier holds two.
     *
     * @param  int[]  $productIds
     * @return array<int, int>
     */
    public static function rpPrices(array $productIds, int $rpType): array
    {
        if (! $productIds) {
            return [];
        }

        return DB::table('selling_prices')
            ->whereIn('product_id', $productIds)
            ->where('type', $rpType)
            ->where('amount', '>', 0)
            ->orderBy('id')
            ->pluck('amount', 'product_id')
            ->map(fn ($cents) => (int) $cents)
            ->all();
    }
}
