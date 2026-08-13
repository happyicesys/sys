<?php

namespace Tests\Unit;

use App\ValueObjects\ApkSettingParameters;
use PHPUnit\Framework\TestCase;

/**
 * Pins the wire contract of the settings payload the deployed fleet
 * (v134/v301/v302) parses. The registry refactor must never change what a
 * bound machine receives: same keys, same canonical order, bools as
 * 'true'/'false' strings, ints as ints, dates as 'Y-m-d' strings or null.
 */
class ApkSettingParametersTest extends TestCase
{
    /**
     * A faithful production row (profile 48, "Brian Testing Server Price")
     * must round-trip with every value preserved and keys in canonical order.
     */
    public function test_production_shaped_row_round_trips_stably(): void
    {
        $row = [
            'poweredBy' => 'Powered By Happy Ice',
            'bannerKind' => 'video',
            'buy1free1X' => 0,
            'buy1free1Y' => 0,
            'buy2free1X' => 1,
            'buy2free1Y' => 0,
            'refund_url' => 'https://sys.happyice.com.sg/refund',
            'companyName' => 'Happy Ice Pte Ltd',
            'company_url' => 'http://www.happyice.com.sg',
            'bundleEndDate' => null,
            'enableP2Price' => 'false',
            'bundleStartDate' => null,
            'company_address' => "2021 Bukit Batok Street 23\n#01-198",
            'enableBuy1Free1' => 'false',
            'enableBuy2Free1' => 'false',
            'enableDebugMode' => 'false',
            'promoBannerKind' => 'video',
            'promoHeaderText' => 'Any 2 Cornetto cones at $6.00. While stocks last.',
            'buy1free1EndDate' => null,
            'buy2free1EndDate' => null,
            'enableDiscount01' => 'false',
            'enableDiscount02' => 'false',
            'enableDiscount03' => 'false',
            'enableLabelPromo' => 'true',
            'promoRunningText' => 'Any 2 Cornetto cones at $6.00. While stocks last.',
            'discountPercent01' => 1,
            'discountPercent02' => 1,
            'discountPercent03' => 1,
            'headerTextEndDate' => '2026-12-31',
            'labelPromoEndDate' => null,
            'supportContactNum' => '85488897',
            'buy1free1StartDate' => null,
            'buy2free1StartDate' => null,
            'runningTextEndDate' => null,
            'disableP1P2CrossGrp' => 'false',
            'headerTextStartDate' => '2026-06-15',
            'labelPromoStartDate' => null,
            'enableBundleDiscount' => 'false',
            'runningTextStartDate' => null,
            'enablePromoHeaderText' => 'true',
            'selectedPricingSource' => 'server',
            'enablePromoRunningText' => 'false',
            'enableHeaderTextRunning' => 'false',
            'dcvendFreePlanPromoValue' => 15,
            'dcvendGoldPlanPromoValue' => 30,
            'dcvendPlatinumPlanPromoValue' => 30,
        ];

        $result = ApkSettingParameters::fromArray($row)->toArray();

        // Every value survives untouched...
        foreach ($row as $key => $value) {
            $this->assertSame($value, $result[$key], "value drifted for {$key}");
        }

        // ...and the output is schema-complete in canonical order.
        $this->assertSame(array_keys(ApkSettingParameters::SCHEMA), array_keys($result));
    }

    public function test_normalization_converges_the_legacy_type_mix(): void
    {
        $result = ApkSettingParameters::fromArray([
            'enableP2Price' => false,          // seeded rows stored real bools
            'enableLabelPromo' => true,
            'discountPercent01' => '10',       // form posts numeric strings
            'buy1free1X' => '1',
            'headerTextStartDate' => 'Invalid date', // client sentinel
            'headerTextEndDate' => '',
            'company_address' => "\n\n",       // blank address join artifact
        ])->toArray();

        $this->assertSame('false', $result['enableP2Price']);
        $this->assertSame('true', $result['enableLabelPromo']);
        $this->assertSame(10, $result['discountPercent01']);
        $this->assertSame(1, $result['buy1free1X']);
        $this->assertNull($result['headerTextStartDate']);
        $this->assertNull($result['headerTextEndDate']);
        $this->assertNull($result['company_address']);
    }

    /**
     * Blank/garbage handling must not change what deployed machines see.
     * Verified against the APK source (Main2Activity.updateSettingsPromoParam):
     * supportContactNum and poweredBy are the ONLY fields where JSON null and
     * "" behave differently on-device (null resurrects hardcoded defaults,
     * "" is a genuine blank) — so '' is preserved for them and only for them.
     */
    public function test_blank_handling_preserves_legacy_wire_semantics(): void
    {
        $result = ApkSettingParameters::fromArray([
            'supportContactNum' => '',
            'poweredBy' => '',
            'promoHeaderText' => '   ',
            'buy2free1X' => '',
            'discountPercent01' => '',
            'bannerKind' => 'holo-display',
        ])->toArray();

        $this->assertSame('', $result['supportContactNum']);
        $this->assertSame('', $result['poweredBy']);
        // Nullable strings ship null (production norm; device-equivalent).
        $this->assertNull($result['promoHeaderText']);
        // A cleared buyXfreeY group key means "no group" (-1) — snapping to
        // the default would aim the promo at a REAL label group.
        $this->assertSame(-1, $result['buy2free1X']);
        // Cleared non-group ints heal to their defaults.
        $this->assertSame(1, $result['discountPercent01']);
        // Unknown enum values pass through verbatim: a newer APK may know an
        // enum value before the registry does, and on-device lookups fall
        // back safely (BannerEnum -> VIDEO).
        $this->assertSame('holo-display', $result['bannerKind']);
    }

    public function test_legacy_null_becomes_minus_one_rule_is_preserved(): void
    {
        $result = ApkSettingParameters::fromArray([
            'buy1free1X' => null,
            'buy1free1Y' => null,
            'buy2free1X' => null,
            'buy2free1Y' => null,
        ])->toArray();

        $this->assertSame(-1, $result['buy1free1X']);
        $this->assertSame(-1, $result['buy1free1Y']);
        $this->assertSame(-1, $result['buy2free1X']);
        $this->assertSame(-1, $result['buy2free1Y']);
    }

    public function test_missing_keys_fall_back_to_defaults_and_unknown_keys_are_dropped(): void
    {
        $result = ApkSettingParameters::fromArray([
            'poweredBy' => 'X',
            'notARealKey' => 'boom',
        ])->toArray();

        $this->assertSame('X', $result['poweredBy']);
        $this->assertArrayNotHasKey('notARealKey', $result);
        $this->assertSame('machine', $result['selectedPricingSource']);
        $this->assertSame(array_keys(ApkSettingParameters::SCHEMA), array_keys($result));
    }

    /**
     * Deprecated keys are grouped for the UI but MUST stay on the wire until
     * the whole fleet is at DEPRECATION_FLEET_APK_VERSION — pre-301 machines
     * still parse them.
     */
    public function test_deprecated_keys_are_still_emitted(): void
    {
        $deprecated = ApkSettingParameters::deprecatedKeys();

        $this->assertSame([
            'enableHeaderTextRunning',
            'enablePromoRunningText',
            'runningTextStartDate',
            'runningTextEndDate',
            'dcvendFreePlanPromoValue',
            'dcvendGoldPlanPromoValue',
            'dcvendPlatinumPlanPromoValue',
        ], $deprecated);

        $payload = ApkSettingParameters::defaults()->toArray();
        foreach ($deprecated as $key) {
            $this->assertArrayHasKey($key, $payload, "deprecated key {$key} fell off the wire");
        }

        $this->assertSame(301, ApkSettingParameters::DEPRECATION_FLEET_APK_VERSION);
    }

    public function test_validation_rules_cover_every_schema_key(): void
    {
        $rules = ApkSettingParameters::validationRules();

        $this->assertSame(array_keys(ApkSettingParameters::SCHEMA), array_keys($rules));
    }

    public function test_ui_schema_carries_deprecation_metadata(): void
    {
        $ui = ApkSettingParameters::uiSchema();

        $this->assertNotNull($ui['dcvendFreePlanPromoValue']['deprecated']);
        $this->assertNull($ui['poweredBy']['deprecated']);
        $this->assertSame('branding', $ui['poweredBy']['group']);
    }
}
