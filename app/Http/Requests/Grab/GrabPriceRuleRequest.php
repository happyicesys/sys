<?php

namespace App\Http\Requests\Grab;

use App\Models\GrabMenuSetting;
use App\Models\SellingPrice;
use App\Services\Grab\Menu\GrabPriceRule;
use App\Services\Grab\Menu\GrabPricingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Grab price rule as the page posts it (company or one store): the mode, the RP tier, the multiplier in
 * plain numbers ("1.25") and the round-up step. The route permission decides who; this decides what.
 */
class GrabPriceRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::ruleFields();
    }

    /** The rule's own fields, shared by every request that carries a rule. */
    public static function ruleFields(string $prefix = '', bool $required = true): array
    {
        $need = $required ? 'required' : 'nullable';

        return [
            "{$prefix}pricing_mode" => [$need, Rule::enum(GrabPricingMode::class)],
            // 0 = each store's own Site tier (GrabPriceRule::SITE_TIER).
            "{$prefix}rp_type" => [$need, 'integer', Rule::in([GrabPriceRule::SITE_TIER, ...array_keys(SellingPrice::TYPE_MAPPINGS)])],
            "{$prefix}multiplier" => ["required_if:{$prefix}pricing_mode,rp_multiplier", 'nullable', 'numeric',
                'min:'.(GrabPriceRule::MIN_MULTIPLIER_BP / 10000), 'max:'.(GrabPriceRule::MAX_MULTIPLIER_BP / 10000)],
            // One multiplier per tier, read when rp_type is the Site's own tier: {"1": 1.25, "2": 1.25, "3": 1, …}.
            "{$prefix}tier_multipliers" => ['nullable', 'array'],
            "{$prefix}tier_multipliers.*" => ['nullable', 'numeric',
                'min:'.(GrabPriceRule::MIN_MULTIPLIER_BP / 10000), 'max:'.(GrabPriceRule::MAX_MULTIPLIER_BP / 10000)],
            "{$prefix}round_up_cents" => [$need, 'integer', Rule::in(GrabMenuSetting::ROUND_UP_STEPS)],
        ];
    }

    /**
     * The rule as stored: the multiplier in basis points (×1.25 → 12500) so prices stay integer cents. A rule
     * that does not multiply keeps ×1.00. A Site-tier multiplier rule also carries one multiplier per tier, and its
     * own multiplier is RP2's — the one a product with no price on its Site's tier is priced by.
     *
     * @return array{pricing_mode: string, rp_type: int, multiplier_bp: int, tier_multipliers_bp: ?array, round_up_cents: int}
     */
    public function ruleValues(): array
    {
        return self::valuesFrom($this->all());
    }

    /**
     * The same, from already validated input (the preview's query string).
     *
     * @return array{pricing_mode: string, rp_type: int, multiplier_bp: int, tier_multipliers_bp: ?array, round_up_cents: int}
     */
    public static function valuesFrom(array $input): array
    {
        $multiplies = ($input['pricing_mode'] ?? null) === GrabPricingMode::RpMultiplier->value && ($input['multiplier'] ?? '') !== '';
        $multiplierBp = $multiplies ? self::bp($input['multiplier']) : 10000;
        $rpType = (int) ($input['rp_type'] ?? 0);

        $tiers = null;
        if ($multiplies && $rpType === GrabPriceRule::SITE_TIER) {
            $tiers = [];
            foreach (array_keys(SellingPrice::TYPE_MAPPINGS) as $tier) {
                $typed = $input['tier_multipliers'][$tier] ?? $input['tier_multipliers'][(string) $tier] ?? null;
                $tiers[$tier] = $typed === null || $typed === '' ? $multiplierBp : self::bp($typed);
            }
            $multiplierBp = $tiers[GrabPriceRule::FALLBACK_TIER];
        }

        return [
            'pricing_mode' => (string) ($input['pricing_mode'] ?? ''),
            'rp_type' => $rpType,
            'multiplier_bp' => $multiplierBp,
            'tier_multipliers_bp' => $tiers,
            'round_up_cents' => (int) ($input['round_up_cents'] ?? 0),
        ];
    }

    // "1.25" → 12500 basis points.
    private static function bp(mixed $multiplier): int
    {
        return (int) round(((float) $multiplier) * 10000);
    }
}
