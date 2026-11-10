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
            "{$prefix}rp_type" => [$need, 'integer', Rule::in(array_keys(SellingPrice::TYPE_MAPPINGS))],
            "{$prefix}multiplier" => ["required_if:{$prefix}pricing_mode,rp_multiplier", 'nullable', 'numeric',
                'min:'.(GrabPriceRule::MIN_MULTIPLIER_BP / 10000), 'max:'.(GrabPriceRule::MAX_MULTIPLIER_BP / 10000)],
            "{$prefix}round_up_cents" => [$need, 'integer', Rule::in(GrabMenuSetting::ROUND_UP_STEPS)],
        ];
    }

    /**
     * The rule as stored: the multiplier in basis points (×1.25 → 12500) so prices stay integer cents. A rule
     * that does not multiply keeps ×1.00.
     *
     * @return array{pricing_mode: string, rp_type: int, multiplier_bp: int, round_up_cents: int}
     */
    public function ruleValues(): array
    {
        return self::valuesFrom($this->all());
    }

    /**
     * The same, from already validated input (the preview's query string).
     *
     * @return array{pricing_mode: string, rp_type: int, multiplier_bp: int, round_up_cents: int}
     */
    public static function valuesFrom(array $input): array
    {
        $multiplies = ($input['pricing_mode'] ?? null) === GrabPricingMode::RpMultiplier->value && ($input['multiplier'] ?? '') !== '';

        return [
            'pricing_mode' => (string) ($input['pricing_mode'] ?? ''),
            'rp_type' => (int) ($input['rp_type'] ?? 0),
            'multiplier_bp' => $multiplies ? (int) round(((float) $input['multiplier']) * 10000) : 10000,
            'round_up_cents' => (int) ($input['round_up_cents'] ?? 0),
        ];
    }
}
