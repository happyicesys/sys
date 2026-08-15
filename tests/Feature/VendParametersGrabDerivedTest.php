<?php

namespace Tests\Feature;

use App\Models\ApkSetting;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `isGrabEnabled` in GET /api/vends/{code}/parameters is DERIVED, not read from
 * the manual vends.is_enable_grab_collection column.
 *
 * A machine can actually take Grab orders when it has an ACTIVE delivery product
 * mapping whose ref number is ACTIVE. The manual flag drifted badly from that:
 * measured over 90 days of live orders, 38 of the 90 flagged machines had no
 * active mapping and had taken no Grab order since 2026-07-06.
 *
 * The regression these tests guard is subtle: DeliveryProductMappingVend carries
 * OperatorDeliveryProductMappingVendFilterScope, and this endpoint is called by
 * the terminal with NO authenticated user. Without withoutGlobalScopes() the
 * viewer scope matches nothing and every machine silently reports Grab-disabled.
 */
class VendParametersGrabDerivedTest extends TestCase
{
    use RefreshDatabase;

    private function makeVend(int $code, bool $manualFlag): Vend
    {
        $vend = Vend::forceCreate([
            'code' => $code,
            'is_enable_grab_collection' => $manualFlag,
        ]);

        // getVendParameters 400s without an attached apk_settings row, so the
        // endpoint would never reach the derivation we are asserting on.
        $setting = ApkSetting::forceCreate([
            'name' => 'test-'.$code,
            'settings_parameter_json' => [],
        ]);
        $vend->apkSettings()->attach($setting->id);

        return $vend;
    }

    private function attachMapping(Vend $vend, bool $mappingActive, int $refStatus): void
    {
        $ref = DeliveryPlatformRefNumber::forceCreate([
            'ref_number' => 'REF-'.$vend->code,
            'status' => $refStatus,
        ]);

        DeliveryProductMappingVend::forceCreate([
            'delivery_product_mapping_id' => 1,
            'vend_id' => $vend->id,
            'vend_code' => $vend->code,
            'is_active' => $mappingActive,
            'delivery_platform_ref_number_id' => $ref->id,
        ]);
    }

    private function grabFlagFor(Vend $vend): ?string
    {
        $response = $this->get('/api/vends/'.$vend->code.'/parameters');

        $response->assertStatus(200);

        return $response->json('isGrabEnabled');
    }

    public function test_active_mapping_with_active_ref_number_enables_grab(): void
    {
        $vend = $this->makeVend(9701, false); // manual flag OFF on purpose
        $this->attachMapping($vend, true, DeliveryPlatformRefNumber::STATUS_ACTIVE);

        $this->assertSame('true', $this->grabFlagFor($vend));
    }

    public function test_inactive_ref_number_disables_grab_even_when_flag_is_on(): void
    {
        $vend = $this->makeVend(9702, true); // manual flag ON — must not win
        $this->attachMapping($vend, true, DeliveryPlatformRefNumber::STATUS_INACTIVE);

        $this->assertSame('false', $this->grabFlagFor($vend));
    }

    public function test_inactive_mapping_disables_grab_even_when_flag_is_on(): void
    {
        $vend = $this->makeVend(9703, true);
        $this->attachMapping($vend, false, DeliveryPlatformRefNumber::STATUS_ACTIVE);

        $this->assertSame('false', $this->grabFlagFor($vend));
    }

    public function test_no_mapping_at_all_disables_grab(): void
    {
        $vend = $this->makeVend(9704, true);

        $this->assertSame('false', $this->grabFlagFor($vend));
    }
}
