<?php

namespace App\Services\Grab\Menu;

use App\Models\GrabMenuSetting;
use App\Models\GrabStorePriceRule;
use InvalidArgumentException;

/**
 * How one Grab store's prices are worked out: a mode (RP tier, RP × multiplier rounded up, or a typed price)
 * and whose rule it is — the operator's (company) or the store's own. Immutable; every Grab price in mark1 is
 * worked out through one of these (GrabPriceList), so the menu Grab pulls, order intake, promotions and the
 * page's preview always agree. All amounts are integer cents.
 */
final class GrabPriceRule
{
    // A multiplier is kept between ×0.50 and ×5.00 (basis points).
    const MIN_MULTIPLIER_BP = 5000;

    const MAX_MULTIPLIER_BP = 50000;

    public function __construct(
        public readonly GrabPricingMode $mode,
        public readonly int $rpType,
        public readonly int $multiplierBp,
        public readonly int $roundUpCents,
        public readonly GrabPriceRuleScope $scope,
        public readonly int $operatorId,
        // Set for a store's own rule: its custom prices are that store's.
        public readonly ?int $refId = null,
    ) {
        if ($scope === GrabPriceRuleScope::Store && $refId === null) {
            throw new InvalidArgumentException('A store price rule names its store.');
        }
        if ($multiplierBp < self::MIN_MULTIPLIER_BP || $multiplierBp > self::MAX_MULTIPLIER_BP) {
            throw new InvalidArgumentException("Multiplier {$multiplierBp} bp is out of range.");
        }
        if (! in_array($roundUpCents, GrabMenuSetting::ROUND_UP_STEPS, true)) {
            throw new InvalidArgumentException("Round-up step {$roundUpCents} is not offered.");
        }
    }

    public static function forCompany(GrabMenuSetting $setting): self
    {
        return new self(
            mode: $setting->pricing_mode,
            rpType: (int) $setting->rp_type,
            multiplierBp: (int) $setting->multiplier_bp,
            roundUpCents: (int) $setting->round_up_cents,
            scope: GrabPriceRuleScope::Company,
            operatorId: (int) $setting->operator_id,
        );
    }

    public static function forStore(GrabStorePriceRule $row, int $operatorId): self
    {
        return new self(
            mode: $row->pricing_mode,
            rpType: (int) $row->rp_type,
            multiplierBp: (int) $row->multiplier_bp,
            roundUpCents: (int) $row->round_up_cents,
            scope: GrabPriceRuleScope::Store,
            operatorId: $operatorId,
            refId: (int) $row->delivery_platform_ref_number_id,
        );
    }

    /**
     * The same pricing as a draft for one store — what the page previews before anything is saved.
     *
     * @param  array{pricing_mode: string, rp_type: int, multiplier_bp: int, round_up_cents: int}  $values
     */
    public static function draftForStore(array $values, int $operatorId, int $refId): self
    {
        return new self(
            mode: GrabPricingMode::from($values['pricing_mode']),
            rpType: (int) $values['rp_type'],
            multiplierBp: (int) $values['multiplier_bp'],
            roundUpCents: (int) $values['round_up_cents'],
            scope: GrabPriceRuleScope::Store,
            operatorId: $operatorId,
            refId: $refId,
        );
    }

    public function isStoreRule(): bool
    {
        return $this->scope === GrabPriceRuleScope::Store;
    }

    public function readsRp(): bool
    {
        return $this->mode->readsRp();
    }

    /**
     * One product's Grab price under this rule, from its RP on the rule's tier and its typed price. Null =
     * no price under this rule, so the product is not sold on Grab (never at $0).
     */
    public function priceFrom(?int $rpCents, ?int $customCents): ?int
    {
        return match ($this->mode) {
            GrabPricingMode::Rp => $rpCents,
            GrabPricingMode::RpMultiplier => $rpCents !== null ? self::multiply($rpCents, $this->multiplierBp, $this->roundUpCents) : null,
            GrabPricingMode::Custom => $customCents,
        };
    }

    // Why a product has no price under this rule: no RP on the tier, or no typed price.
    public function missingReason(): string
    {
        return $this->mode === GrabPricingMode::Custom ? GrabPriceList::NO_CUSTOM_PRICE : GrabPriceList::NO_RP;
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

    // What the page shows and posts back: the same keys as the forms.
    public function toArray(): array
    {
        return [
            'scope' => $this->scope->value,
            'pricing_mode' => $this->mode->value,
            'rp_type' => $this->rpType,
            'multiplier' => round($this->multiplierBp / 10000, 4),
            'round_up_cents' => $this->roundUpCents,
        ];
    }
}
