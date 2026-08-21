<?php

namespace App\ValueObjects;

use JsonSerializable;

/**
 * Single source of truth for the UI Setting (Marketing & Campaign Remote
 * Setting) parameter schema stored in apk_settings.settings_parameter_json
 * and pushed to machines via TYPESYNCSETTINGSPARAM.
 *
 * SCHEMA is a declarative registry: every key the APK contract knows, with its
 * default, type, UI grouping and (where applicable) deprecation metadata.
 * Everything else in this class — defaults, normalization, validation rules,
 * the UI schema for the edit page — derives from it. To add a setting, add ONE
 * entry to SCHEMA. Reads of old DB rows self-heal with the default; nothing is
 * written back until the user saves.
 *
 * Wire-compatibility rules (the deployed fleet, v134/v301/v302, parses this
 * payload with Gson — the shape below is what all 33 production rows already
 * dominantly store, so normalization converges rows without changing what any
 * machine can parse):
 *   - TYPE_BOOL serializes as the STRINGS 'true'/'false', never real JSON
 *     booleans. Every production row stores strings for every enable key, and
 *     Setting/Parameter.vue matches them with booleanStrictOptions by ===.
 *   - TYPE_INT serializes as a real JSON int (numeric strings are coerced —
 *     4 production rows carry "5"/"10"/"15" from form posts).
 *   - TYPE_DATE serializes as a 'Y-m-d' string or null ('' and the client's
 *     'Invalid date' sentinel both become null).
 *   - buy1free1X/Y and buy2free1X/Y keep the legacy "null becomes -1" rule.
 *   - A key missing from the input falls back to its SCHEMA default.
 *   - Unknown keys are dropped.
 *   - Output key order is the canonical SCHEMA order, so payloads stay
 *     byte-stable for the APK.
 *
 * Deprecation: keys flagged 'deprecated' are STILL stored, still emitted in
 * /parameters, and still normalized — machines below
 * DEPRECATION_FLEET_APK_VERSION read them. They are only grouped into the
 * "Deprecated" section of the edit page. Once every bound machine reports
 * apk version >= DEPRECATION_FLEET_APK_VERSION the keys can be removed from
 * SCHEMA entirely (one deletion here removes storage, payload and UI at once).
 */
final class ApkSettingParameters implements JsonSerializable
{
    public const TYPE_BOOL = 'bool';

    public const TYPE_INT = 'int';

    public const TYPE_DATE = 'date';

    public const TYPE_STRING = 'string';

    public const TYPE_ENUM = 'enum';

    /**
     * Fleet APK versionCode at which every 'deprecated' key below can be
     * dropped from SCHEMA (per Brian, 2026-08-13: DCVend is scrapped and the
     * running-text block is dead on-device; both are kept on the wire only
     * for machines older than v301).
     */
    public const DEPRECATION_FLEET_APK_VERSION = 301;

    /**
     * Canonical registry. Key order IS the wire order — do not re-sort.
     *
     * Entry shape:
     *   default    mixed   value used when the key is absent
     *   type       string  one of the TYPE_* constants
     *   group      string  UI section on the edit page
     *   label      string  human label for generated/summarized UI
     *   options    array   TYPE_ENUM only: allowed values
     *   deprecated array   only on deprecated keys: ['since' => 'Y-m-d',
     *                      'note' => why / what replaces it]
     *
     * @var array<string, array<string, mixed>>
     */
    public const SCHEMA = [
        'enablePromoHeaderText' => ['default' => 'false', 'type' => self::TYPE_BOOL, 'group' => 'campaign', 'label' => 'Enable Campaign Advertisement'],
        'promoHeaderText' => ['default' => null, 'type' => self::TYPE_STRING, 'group' => 'campaign', 'label' => 'Campaign Running Text 1 (Main Page)'],
        'promoBannerKind' => ['default' => 'video', 'type' => self::TYPE_ENUM, 'group' => 'campaign', 'label' => 'Campaign Background Video/Picture', 'options' => ['video', 'picture', 'mixed']],
        'headerTextStartDate' => ['default' => null, 'type' => self::TYPE_DATE, 'group' => 'campaign', 'label' => 'Campaign Start Date'],
        'headerTextEndDate' => ['default' => null, 'type' => self::TYPE_DATE, 'group' => 'campaign', 'label' => 'Campaign End Date'],

        'promoRunningText' => ['default' => null, 'type' => self::TYPE_STRING, 'group' => 'campaign', 'label' => 'Campaign Running Text 2 (Soft Keypad)'],

        // Running-text block: dead end-to-end. Only ever editable on the
        // orphaned /settings/vend/{id}/parameter page, and on the APK the
        // gating pref is read but never written (CartFragment marquee can
        // never show). Kept on the wire for pre-301 machines only.
        'enableHeaderTextRunning' => ['default' => 'false', 'type' => self::TYPE_BOOL, 'group' => 'campaign', 'label' => 'Enable Header Text Running', 'deprecated' => ['since' => '2026-08-13', 'note' => 'Dead on-device; no working APK reads it.']],
        'enablePromoRunningText' => ['default' => 'false', 'type' => self::TYPE_BOOL, 'group' => 'campaign', 'label' => 'Enable Promo Running Text', 'deprecated' => ['since' => '2026-08-13', 'note' => 'Dead on-device; the cart marquee pref is never written by any APK.']],
        'runningTextStartDate' => ['default' => null, 'type' => self::TYPE_DATE, 'group' => 'campaign', 'label' => 'Running Text Start Date', 'deprecated' => ['since' => '2026-08-13', 'note' => 'Dead on-device.']],
        'runningTextEndDate' => ['default' => null, 'type' => self::TYPE_DATE, 'group' => 'campaign', 'label' => 'Running Text End Date', 'deprecated' => ['since' => '2026-08-13', 'note' => 'Dead on-device.']],

        'enableP2Price' => ['default' => 'false', 'type' => self::TYPE_BOOL, 'group' => 'pricing', 'label' => 'Enable P2 Price from VMC'],
        'disableP1P2CrossGrp' => ['default' => 'false', 'type' => self::TYPE_BOOL, 'group' => 'pricing', 'label' => 'Disable P1P2 Cross Group Discount'],

        'enableBuy1Free1' => ['default' => 'false', 'type' => self::TYPE_BOOL, 'group' => 'promotion', 'label' => 'Enable Buy 1 Free 1'],
        'buy1free1X' => ['default' => 0, 'type' => self::TYPE_INT, 'group' => 'promotion', 'label' => 'Buy 1 Free 1 — Buy Group'],
        'buy1free1Y' => ['default' => 0, 'type' => self::TYPE_INT, 'group' => 'promotion', 'label' => 'Buy 1 Free 1 — Free Group'],
        'buy1free1StartDate' => ['default' => null, 'type' => self::TYPE_DATE, 'group' => 'promotion', 'label' => 'Buy 1 Free 1 Start Date'],
        'buy1free1EndDate' => ['default' => null, 'type' => self::TYPE_DATE, 'group' => 'promotion', 'label' => 'Buy 1 Free 1 End Date'],

        'enableBuy2Free1' => ['default' => 'false', 'type' => self::TYPE_BOOL, 'group' => 'promotion', 'label' => 'Enable Buy 2 Free 1'],
        'buy2free1X' => ['default' => 1, 'type' => self::TYPE_INT, 'group' => 'promotion', 'label' => 'Buy 2 Free 1 — Buy Group'],
        'buy2free1Y' => ['default' => 0, 'type' => self::TYPE_INT, 'group' => 'promotion', 'label' => 'Buy 2 Free 1 — Free Group'],
        'buy2free1StartDate' => ['default' => null, 'type' => self::TYPE_DATE, 'group' => 'promotion', 'label' => 'Buy 2 Free 1 Start Date'],
        'buy2free1EndDate' => ['default' => null, 'type' => self::TYPE_DATE, 'group' => 'promotion', 'label' => 'Buy 2 Free 1 End Date'],

        'enableBundleDiscount' => ['default' => 'false', 'type' => self::TYPE_BOOL, 'group' => 'promotion', 'label' => 'Enable Bundle Discount'],
        'bundleStartDate' => ['default' => null, 'type' => self::TYPE_DATE, 'group' => 'promotion', 'label' => 'Bundle Start Date'],
        'bundleEndDate' => ['default' => null, 'type' => self::TYPE_DATE, 'group' => 'promotion', 'label' => 'Bundle End Date'],
        'enableDiscount01' => ['default' => 'true', 'type' => self::TYPE_BOOL, 'group' => 'promotion', 'label' => 'Enable Discount Tier 1'],
        'discountPercent01' => ['default' => 1, 'type' => self::TYPE_INT, 'group' => 'promotion', 'label' => 'Discount Tier 1 %'],
        'enableDiscount02' => ['default' => 'false', 'type' => self::TYPE_BOOL, 'group' => 'promotion', 'label' => 'Enable Discount Tier 2'],
        'discountPercent02' => ['default' => 1, 'type' => self::TYPE_INT, 'group' => 'promotion', 'label' => 'Discount Tier 2 %'],
        'enableDiscount03' => ['default' => 'false', 'type' => self::TYPE_BOOL, 'group' => 'promotion', 'label' => 'Enable Discount Tier 3'],
        'discountPercent03' => ['default' => 1, 'type' => self::TYPE_INT, 'group' => 'promotion', 'label' => 'Discount Tier 3 %'],

        'enableLabelPromo' => ['default' => 'false', 'type' => self::TYPE_BOOL, 'group' => 'promotion', 'label' => 'Enable Label Promo'],
        'labelPromoStartDate' => ['default' => null, 'type' => self::TYPE_DATE, 'group' => 'promotion', 'label' => 'Label Promo Start Date'],
        'labelPromoEndDate' => ['default' => null, 'type' => self::TYPE_DATE, 'group' => 'promotion', 'label' => 'Label Promo End Date'],

        'bannerKind' => ['default' => 'picture', 'type' => self::TYPE_ENUM, 'group' => 'branding', 'label' => 'Background Video/Picture', 'options' => ['video', 'picture', 'mixed']],
        // Default corrected 2026-08-13: 23 of 33 production rows and the
        // deployed devices carry 85488897; 87188597 was the stale old number.
        // Existing rows are untouched (the key exists on all of them).
        'supportContactNum' => ['default' => '85488897', 'type' => self::TYPE_STRING, 'group' => 'branding', 'label' => 'Support Contact Number'],
        'poweredBy' => ['default' => 'Powered By Happy Ice', 'type' => self::TYPE_STRING, 'group' => 'branding', 'label' => 'Display Text (Corner)'],

        // 2026-08-21: the STORED value is no longer what a machine receives.
        // Pricing source is per machine (vends.is_using_server_price — "follow
        // the Site's pricing" or the board price); VendController::
        // getVendParameters overrides this key per vend on the wire. The key
        // stays here so old rows normalize and the payload stays schema-
        // complete for Gson (a missing key resets the pref on-device).
        'selectedPricingSource' => ['default' => 'machine', 'type' => self::TYPE_ENUM, 'group' => 'pricing', 'label' => 'Pricing Source (derived per machine)', 'options' => ['server', 'machine']],

        'enableDebugMode' => ['default' => 'false', 'type' => self::TYPE_BOOL, 'group' => 'system', 'label' => 'Enable Debug Mode'],

        // DCVend is scrapped (decision 2026-08-13, see the revamp audit doc).
        // Values are still parsed by every deployed APK's settings DTO, so
        // they stay on the wire until the fleet is >= v301.
        'dcvendFreePlanPromoValue' => ['default' => 15, 'type' => self::TYPE_INT, 'group' => 'deprecated', 'label' => 'DCVend Free Plan Promo Rate', 'deprecated' => ['since' => '2026-08-13', 'note' => 'DCVend feature scrapped.']],
        'dcvendGoldPlanPromoValue' => ['default' => 30, 'type' => self::TYPE_INT, 'group' => 'deprecated', 'label' => 'DCVend Gold Plan Promo Rate', 'deprecated' => ['since' => '2026-08-13', 'note' => 'DCVend feature scrapped.']],
        'dcvendPlatinumPlanPromoValue' => ['default' => 30, 'type' => self::TYPE_INT, 'group' => 'deprecated', 'label' => 'DCVend Platinum Plan Promo Rate', 'deprecated' => ['since' => '2026-08-13', 'note' => 'DCVend feature scrapped.']],

        'company_url' => ['default' => null, 'type' => self::TYPE_STRING, 'group' => 'company', 'label' => 'Company URL'],
        'company_address' => ['default' => null, 'type' => self::TYPE_STRING, 'group' => 'company', 'label' => 'Company Address'],
        'refund_url' => ['default' => null, 'type' => self::TYPE_STRING, 'group' => 'company', 'label' => 'Refund URL'],
        'companyName' => ['default' => null, 'type' => self::TYPE_STRING, 'group' => 'company', 'label' => 'Company Name'],
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
     * @param  array<string, mixed>  $parameters  MUST already be schema-complete
     *                                            and normalized; use fromArray().
     */
    private function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Build from any partial/complete/legacy array (request input, old DB
     * rows, ...). Missing keys get defaults, unknown keys are dropped, and
     * every value is normalized to its canonical wire type.
     *
     * @param  array<string, mixed>|null  $input
     */
    public static function fromArray(?array $input): self
    {
        $input = $input ?? [];
        $parameters = [];

        foreach (self::SCHEMA as $key => $definition) {
            $parameters[$key] = array_key_exists($key, $input)
                ? self::normalize($key, $definition, $input[$key])
                : $definition['default'];
        }

        return new self($parameters);
    }

    public static function defaults(): self
    {
        return self::fromArray(null);
    }

    /**
     * Coerce one raw value to the canonical wire shape for its type. See the
     * class docblock for why bools are 'true'/'false' STRINGS.
     */
    private static function normalize(string $key, array $definition, mixed $value): mixed
    {
        // Arrays/objects can only arrive from a pathological DB row (HTTP
        // input is shielded by validation) — heal to the default instead of
        // fataling on an array-to-string conversion mid-request.
        if ($value !== null && ! is_scalar($value)) {
            $value = $definition['default'];
        }

        switch ($definition['type']) {
            case self::TYPE_BOOL:
                if ($value === null) {
                    return $definition['default'];
                }

                return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';

            case self::TYPE_INT:
                if (! is_numeric($value)) {
                    // Non-numeric covers null AND ''/garbage (write paths
                    // without ConvertEmptyStringsToNull). For the buyXfreeY
                    // group keys "cleared" must mean -1 = no group — snapping
                    // to the default would point the promo at a REAL label
                    // group.
                    return in_array($key, self::NULL_BECOMES_MINUS_ONE, true)
                        ? -1
                        : $definition['default'];
                }

                return (int) $value;

            case self::TYPE_DATE:
                if ($value === null || $value === '' || $value === 'Invalid date') {
                    return null;
                }

                return (string) $value;

            case self::TYPE_ENUM:
                if ($value === null || $value === '') {
                    return $definition['default'];
                }

                // Unknown non-empty values pass through VERBATIM: a newer APK
                // may understand an enum value before this registry learns it,
                // and on-device enum lookups fall back safely (BannerEnum →
                // VIDEO). Snapping to the default here would silently rewrite
                // e.g. selectedPricingSource on read — the exact hazard
                // mergeCampaignParameter() exists to prevent.
                return (string) $value;

            case self::TYPE_STRING:
            default:
                if ($value === null) {
                    return null;
                }

                $value = (string) $value;

                if (trim($value) !== '') {
                    return $value;
                }

                // Blank handling must not change what deployed machines see:
                // keys whose schema default is non-null (supportContactNum,
                // poweredBy) have always shipped '' verbatim when cleared —
                // keep that. Nullable keys ship null, matching the production
                // norm and fixing the "\n\n" blank-address artifact.
                return $definition['default'] === null ? null : $value;
        }
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
        return array_key_exists($key, self::SCHEMA);
    }

    /**
     * Keys present in SCHEMA but absent from the given raw array —
     * i.e. what a stale DB row is missing.
     *
     * @param  array<string, mixed>|null  $raw
     * @return array<int, string>
     */
    public static function missingKeys(?array $raw): array
    {
        $raw = $raw ?? [];

        return array_values(array_diff(array_keys(self::SCHEMA), array_keys($raw)));
    }

    /**
     * @return array<int, string> keys flagged deprecated, in canonical order
     */
    public static function deprecatedKeys(): array
    {
        return array_keys(array_filter(
            self::SCHEMA,
            fn (array $definition) => isset($definition['deprecated'])
        ));
    }

    /**
     * Schema metadata for the edit page: label/group/type/deprecation per key
     * (no defaults — the page reads values from the row itself).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function uiSchema(): array
    {
        $out = [];

        foreach (self::SCHEMA as $key => $definition) {
            $out[$key] = [
                'label' => $definition['label'],
                'group' => $definition['group'],
                'type' => $definition['type'],
                'options' => $definition['options'] ?? null,
                'deprecated' => $definition['deprecated'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Laravel validation rules derived from the registry, permissive enough
     * for everything the current edit form legitimately posts (string bools
     * from selects, numeric strings from number inputs, null-cleared dates).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(): array
    {
        $rules = [];

        foreach (self::SCHEMA as $key => $definition) {
            $rules[$key] = match ($definition['type']) {
                self::TYPE_BOOL => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                    if (! is_bool($value) && ! in_array($value, ['true', 'false'], true)) {
                        $fail("The {$attribute} field must be true or false.");
                    }
                }],
                self::TYPE_INT => ['nullable', 'integer'],
                self::TYPE_DATE => ['nullable', 'string', 'max:20'],
                self::TYPE_ENUM => ['nullable', 'in:'.implode(',', $definition['options'])],
                default => ['nullable', 'string', 'max:1000'],
            };
        }

        return $rules;
    }

    public function jsonSerialize(): array
    {
        return $this->parameters;
    }
}
