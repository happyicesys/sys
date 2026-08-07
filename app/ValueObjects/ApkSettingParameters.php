<?php

namespace App\ValueObjects;

use JsonSerializable;

/**
 * Single source of truth for the UI Setting (Marketing & Campaign Remote
 * Setting) parameter schema stored in apk_settings.settings_parameter_json
 * and pushed to machines via TYPESYNCSETTINGSPARAM.
 *
 * Rules (deliberately behaviour-preserving vs the old
 * VendParameterService::getCampaignParameter()):
 *   - A key present in the input passes through VERBATIM (no type coercion —
 *     machines already accept the historical mix of bool/string/number).
 *   - buy1free1X/Y and buy2free1X/Y keep the legacy "null becomes -1" rule.
 *   - A key missing from the input falls back to its DEFAULTS value
 *     (previously this fataled with "Undefined array key" for most keys).
 *   - Unknown keys are dropped (the old method whitelisted too).
 *   - Output key order is the canonical order of DEFAULTS, so payloads stay
 *     byte-stable for the APK.
 *
 * To add a new setting: add ONE entry to DEFAULTS. Reads of old DB rows
 * self-heal with the default; nothing is written back until the user saves.
 */
final class ApkSettingParameters implements JsonSerializable
{
    /**
     * Canonical schema: every key the APK contract knows, with the default
     * used when the key is absent (mirrors the old getDefaultParameter()).
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'enablePromoHeaderText' => false,
        'promoHeaderText' => null,
        'promoBannerKind' => 'video',
        'headerTextStartDate' => null,
        'headerTextEndDate' => null,

        'promoRunningText' => null,

        // Running-text block. Present on ALL 1115 vends.settings_parameter_json
        // rows and bound by Setting/Parameter.vue (v-model at :83/:151/:177/:190,
        // submitted at :749/:751), but absent from the old getDefaultParameter()
        // - so leaving them out of DEFAULTS meant fromArray() dropped them and
        // the first successful save wiped the setting off every machine.
        //
        // Defaults are the STRINGS 'false', not booleans: that is what all 1115
        // rows store, and Parameter.vue matches them with
        // booleanStrictOptions ({id: 'true'} / {id: 'false'}) by ===, so a
        // boolean here would render the selects blank.
        'enableHeaderTextRunning' => 'false',
        'enablePromoRunningText' => 'false',
        'runningTextStartDate' => null,
        'runningTextEndDate' => null,

        'enableP2Price' => false,
        'disableP1P2CrossGrp' => false,

        'enableBuy1Free1' => false,
        'buy1free1X' => 0,
        'buy1free1Y' => 0,
        'buy1free1StartDate' => null,
        'buy1free1EndDate' => null,

        'enableBuy2Free1' => false,
        'buy2free1X' => 1,
        'buy2free1Y' => 0,
        'buy2free1StartDate' => null,
        'buy2free1EndDate' => null,

        'enableBundleDiscount' => false,
        'bundleStartDate' => null,
        'bundleEndDate' => null,
        'enableDiscount01' => true,
        'discountPercent01' => 1,
        'enableDiscount02' => false,
        'discountPercent02' => 1,
        'enableDiscount03' => false,
        'discountPercent03' => 1,

        'enableLabelPromo' => false,
        'labelPromoStartDate' => null,
        'labelPromoEndDate' => null,

        'bannerKind' => 'picture',
        'supportContactNum' => '87188597',
        'poweredBy' => 'Powered By Happy Ice',

        'selectedPricingSource' => 'machine',

        'enableDebugMode' => false,

        'dcvendFreePlanPromoValue' => 15,
        'dcvendGoldPlanPromoValue' => 30,
        'dcvendPlatinumPlanPromoValue' => 30,

        'company_url' => null,
        'company_address' => null,
        'refund_url' => null,
        'companyName' => null,
    ];

    /**
     * Legacy rule: these keys turn an explicit null into -1
     * (old code: $parameters['buy1free1X'] ?? -1).
     *
     * @var array<int, string>
     */
    private const NULL_BECOMES_MINUS_ONE = [
        'buy1free1X',
        'buy1free1Y',
        'buy2free1X',
        'buy2free1Y',
    ];

    /** @var array<string, mixed> */
    private array $parameters;

    /**
     * @param array<string, mixed> $parameters MUST already be schema-complete;
     *                                         use fromArray() / defaults().
     */
    private function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Build from any partial/complete/legacy array (request input, old DB
     * rows, ...). Missing keys get defaults, unknown keys are dropped.
     *
     * @param array<string, mixed>|null $input
     */
    public static function fromArray(?array $input): self
    {
        $input = $input ?? [];
        $parameters = [];

        foreach (self::DEFAULTS as $key => $default) {
            if (array_key_exists($key, $input)) {
                $value = $input[$key];

                if ($value === null && in_array($key, self::NULL_BECOMES_MINUS_ONE, true)) {
                    $value = -1;
                }

                $parameters[$key] = $value;
            } else {
                $parameters[$key] = $default;
            }
        }

        return new self($parameters);
    }

    public static function defaults(): self
    {
        return new self(self::DEFAULTS);
    }

    /**
     * The full, schema-complete parameter array in canonical key order.
     * This is the exact payload shape the APK already receives today.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->parameters;
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        return $this->parameters[$key] ?? $fallback;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, self::DEFAULTS);
    }

    /**
     * Keys present in DEFAULTS but absent from the given raw array —
     * i.e. what a stale DB row is missing.
     *
     * @param array<string, mixed>|null $raw
     * @return array<int, string>
     */
    public static function missingKeys(?array $raw): array
    {
        $raw = $raw ?? [];

        return array_values(array_diff(array_keys(self::DEFAULTS), array_keys($raw)));
    }

    public function jsonSerialize(): array
    {
        return $this->parameters;
    }
}
