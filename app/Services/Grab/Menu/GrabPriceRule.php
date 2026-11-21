<?php

namespace App\Services\Grab\Menu;

use App\Models\GrabMenuSetting;
use App\Models\GrabStorePriceRule;
use App\Models\SellingPrice;
use InvalidArgumentException;

/**
 * How one Grab store's prices are worked out: a mode (RP tier, RP × multiplier rounded up, or a typed price)
 * and whose rule it is — the operator's (company) or the store's own. Immutable; every Grab price in mark1 is
 * worked out through one of these (GrabPriceList), so the menu Grab pulls, order intake, promotions and the
 * page's preview always agree. All amounts are integer cents.
 *
 * The tier is either one named tier for every store (rpType 1–5), or SITE_TIER (0): each store takes its machine's
 * Site tier (customers.selling_price_type) with that tier's own multiplier (Brian, 2026-11-21: RP2 × 1.25 but
 * RP3 × 1, since RP3 is already dearer). A product with no price on the Site's tier, or a Site with no tier, uses
 * its RP2 price and RP2's multiplier, so it stays on Grab.
 */
final class GrabPriceRule
{
    // A multiplier is kept between ×0.50 and ×5.00 (basis points).
    const MIN_MULTIPLIER_BP = 5000;

    const MAX_MULTIPLIER_BP = 50000;

    // rpType 0: each store reads its own Site's tier.
    const SITE_TIER = 0;

    // The tier a Site-tier rule falls back to (no tier on the Site, or no price on it).
    const FALLBACK_TIER = SellingPrice::TYPE_2;

    // Tier => multiplier in basis points, kept only for a Site-tier multiplier rule; a tier not listed uses multiplierBp.
    /** @var array<int, int> */
    public readonly array $tierMultipliersBp;

    public function __construct(
        public readonly GrabPricingMode $mode,
        public readonly int $rpType,
        public readonly int $multiplierBp,
        public readonly int $roundUpCents,
        public readonly GrabPriceRuleScope $scope,
        public readonly int $operatorId,
        // Set for a store's own rule: its custom prices are that store's.
        public readonly ?int $refId = null,
        ?array $tierMultipliersBp = null,
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
        $tiers = [];
        if ($rpType === self::SITE_TIER && $mode === GrabPricingMode::RpMultiplier) {
            foreach ($tierMultipliersBp ?? [] as $tier => $bp) {
                if (! array_key_exists((int) $tier, SellingPrice::TYPE_MAPPINGS) || $bp === null || $bp === '') {
                    continue;
                }
                $bp = (int) $bp;
                if ($bp < self::MIN_MULTIPLIER_BP || $bp > self::MAX_MULTIPLIER_BP) {
                    throw new InvalidArgumentException("Multiplier {$bp} bp for RP{$tier} is out of range.");
                }
                $tiers[(int) $tier] = $bp;
            }
            ksort($tiers);
        }
        $this->tierMultipliersBp = $tiers;
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
            tierMultipliersBp: $setting->tier_multipliers_json,
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
            tierMultipliersBp: $row->tier_multipliers_json,
        );
    }

    /**
     * The same pricing as a draft for one store — what the page previews before anything is saved.
     *
     * @param  array{pricing_mode: string, rp_type: int, multiplier_bp: int, round_up_cents: int, tier_multipliers_bp?: ?array}  $values
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
            tierMultipliersBp: $values['tier_multipliers_bp'] ?? null,
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

    // Each store reads its own Site's tier.
    public function usesSiteTier(): bool
    {
        return $this->mode->readsRp() && $this->rpType === self::SITE_TIER;
    }

    // The tier this rule prices a store by, given its Site's tier (null = the Site has none or is not known).
    public function tierFor(?int $siteTier): int
    {
        if (! $this->usesSiteTier()) {
            return $this->rpType;
        }

        return $siteTier !== null && array_key_exists($siteTier, SellingPrice::TYPE_MAPPINGS) ? $siteTier : self::FALLBACK_TIER;
    }

    // The multiplier on one tier, in basis points.
    public function multiplierFor(int $tier): int
    {
        return $this->usesSiteTier() ? ($this->tierMultipliersBp[$tier] ?? $this->multiplierBp) : $this->multiplierBp;
    }

    /**
     * One product's Grab price under this rule, from its RP on $tier (the rule's own tier unless it reads the Site's)
     * and its typed price. Null = no price under this rule, so the product is not sold on Grab (never at $0).
     */
    public function priceFrom(?int $rpCents, ?int $customCents, ?int $tier = null): ?int
    {
        $tier ??= $this->tierFor(null);

        return match ($this->mode) {
            GrabPricingMode::Rp => $rpCents,
            GrabPricingMode::RpMultiplier => $rpCents !== null ? self::multiply($rpCents, $this->multiplierFor($tier), $this->roundUpCents) : null,
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

    /**
     * In words for the Grab Menu Sync log: "RP2", "RP2 × 1.25, round up 10¢", "Site RP (RP1 × 1.3, RP2 × 1.25 …),
     * round up 10¢", "Typed prices". Not translated: it is stored with the log row.
     */
    public function label(): string
    {
        $times = fn (int $bp) => rtrim(rtrim(number_format($bp / 10000, 4, '.', ''), '0'), '.');
        $step = ', round up '.($this->roundUpCents >= 100 ? '$'.intdiv($this->roundUpCents, 100) : $this->roundUpCents.'¢');
        $tiers = collect(SellingPrice::TYPE_MAPPINGS)->keys()
            ->map(fn ($tier) => 'RP'.$tier.' × '.$times($this->multiplierFor((int) $tier)))->implode(', ');

        return match ($this->mode) {
            GrabPricingMode::Rp => $this->usesSiteTier() ? 'Site RP' : 'RP'.$this->rpType,
            GrabPricingMode::RpMultiplier => $this->usesSiteTier()
                ? 'Site RP ('.$tiers.')'.$step
                : 'RP'.$this->rpType.' × '.$times($this->multiplierBp).$step,
            GrabPricingMode::Custom => 'Typed prices',
        };
    }

    // What the page shows and posts back: the same keys as the forms.
    public function toArray(): array
    {
        return [
            'scope' => $this->scope->value,
            'pricing_mode' => $this->mode->value,
            'rp_type' => $this->rpType,
            'multiplier' => round($this->multiplierBp / 10000, 4),
            'tier_multipliers' => self::tierMultipliersForPage($this->tierMultipliersBp, $this->multiplierBp),
            'round_up_cents' => $this->roundUpCents,
        ];
    }

    /**
     * Every tier's multiplier as the form edits it ({"1": 1.25, …, "5": 1}): a tier the rule does not name shows
     * the rule's own multiplier, which is what it is priced at.
     *
     * @param  array<int|string, int>|null  $tierMultipliersBp
     * @return array<string, float>
     */
    public static function tierMultipliersForPage(?array $tierMultipliersBp, int $multiplierBp): array
    {
        $out = [];
        foreach (array_keys(SellingPrice::TYPE_MAPPINGS) as $tier) {
            $out[(string) $tier] = round(((int) ($tierMultipliersBp[$tier] ?? $tierMultipliersBp[(string) $tier] ?? $multiplierBp)) / 10000, 4);
        }

        return $out;
    }
}
