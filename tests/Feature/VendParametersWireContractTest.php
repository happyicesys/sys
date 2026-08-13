<?php

namespace Tests\Feature;

use App\Models\ApkSetting;
use App\Models\Vend;
use App\ValueObjects\ApkSettingParameters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Wire contract of GET /api/vends/{code}/parameters/{apkver?} — the payload
 * every deployed mark1-apk parses with Gson 2.8 into clsSettingPromoParam
 * (primitive boolean/int fields, String fields, initialized defaults).
 *
 * What keeps OLD revisions safe, and what these tests pin:
 *   - The payload is a SUPERSET forever: every SCHEMA key is always present.
 *     Gson fills a missing field with the DTO default and the APK then writes
 *     that default over the machine's pref — so dropping a key silently
 *     resets fleets. Adding keys is free (Gson ignores unknown JSON).
 *   - Types stay Gson-coercible: bools as 'true'/'false' strings, ints as
 *     real ints (never non-numeric strings — JsonReader.nextInt() throws and
 *     the APK's catch block then discards the WHOLE settings update).
 *   - promoLabelItems must survive for pre-213 APKs and stay stripped for
 *     213+ (they migrated to campaigns[]).
 */
class VendParametersWireContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Simulates a stale production row written years ago: raw JSON with the
     * historical type mix (real bools, stringly ints), missing newer keys,
     * and a junk key — bypassing the model cast the way old rows do.
     */
    private function makeVendWithLegacyRow(): Vend
    {
        $vend = Vend::forceCreate(['code' => 9901]);

        $legacyJson = [
            'enablePromoHeaderText' => true,     // seeded rows stored real bools
            'promoHeaderText' => 'Legacy promo',
            'enableP2Price' => 'false',          // form-edited rows stored strings
            'discountPercent01' => '5',          // stringly int from an old form post
            'buy1free1X' => null,                // legacy null → must become -1
            'selectedPricingSource' => 'server',
            'thisKeyWasRemovedLongAgo' => 'junk',
            // note: no dcvend*, no running-text block, no company fields —
            // the 38-key generation.
        ];

        $settingId = DB::table('apk_settings')->insertGetId([
            'name' => 'Legacy profile',
            'settings_parameter_json' => json_encode($legacyJson),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('apk_setting_vend')->insert([
            'apk_setting_id' => $settingId,
            'vend_id' => $vend->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $vend;
    }

    public function test_payload_is_schema_complete_with_gson_safe_types(): void
    {
        $vend = $this->makeVendWithLegacyRow();

        $data = $this->getJson('/api/vends/9901/parameters/301')
            ->assertOk()
            ->json();

        foreach (ApkSettingParameters::SCHEMA as $key => $definition) {
            $this->assertArrayHasKey($key, $data, "SCHEMA key {$key} fell off the wire — old APKs would reset it to the DTO default");

            $value = $data[$key];

            switch ($definition['type']) {
                case ApkSettingParameters::TYPE_BOOL:
                    $this->assertContains($value, ['true', 'false'], "{$key} must be a 'true'/'false' string");
                    break;
                case ApkSettingParameters::TYPE_INT:
                    $this->assertIsInt($value, "{$key} must be a real JSON int (a non-numeric string aborts the whole update on-device)");
                    break;
                case ApkSettingParameters::TYPE_DATE:
                    $this->assertTrue($value === null || is_string($value), "{$key} must be a string or null");
                    break;
                case ApkSettingParameters::TYPE_ENUM:
                    $this->assertContains($value, $definition['options'], "{$key} out of enum range");
                    break;
            }
        }

        // Legacy values survive with their meaning intact.
        $this->assertSame('true', $data['enablePromoHeaderText']);
        $this->assertSame('Legacy promo', $data['promoHeaderText']);
        $this->assertSame(5, $data['discountPercent01']);
        $this->assertSame(-1, $data['buy1free1X']);
        $this->assertSame('server', $data['selectedPricingSource']);

        // Keys the 38-key generation never had are healed with defaults,
        // deprecated ones included (pre-301 machines still parse them).
        $this->assertSame(15, $data['dcvendFreePlanPromoValue']);
        $this->assertSame('false', $data['enableHeaderTextRunning']);

        // Junk keys never reach a machine.
        $this->assertArrayNotHasKey('thisKeyWasRemovedLongAgo', $data);
    }

    public function test_computed_alias_keys_are_present(): void
    {
        $this->makeVendWithLegacyRow();

        $data = $this->getJson('/api/vends/9901/parameters/301')
            ->assertOk()
            ->json();

        // clsSettingPromoParam binds the camelCase aliases, not the
        // snake_case storage keys — both must keep shipping.
        foreach (['isGrabEnabled', 'companyUrl', 'companyAddress', 'companyName', 'refundUrl', 'campaigns'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }

        $this->assertContains($data['isGrabEnabled'], ['true', 'false']);
    }

    public function test_promo_label_items_kept_for_pre_213_and_stripped_after(): void
    {
        $this->makeVendWithLegacyRow();

        $this->assertArrayHasKey(
            'promoLabelItems',
            $this->getJson('/api/vends/9901/parameters/212')->assertOk()->json(),
            'pre-213 APKs read promoLabelItems'
        );

        $this->assertArrayNotHasKey(
            'promoLabelItems',
            $this->getJson('/api/vends/9901/parameters/213')->assertOk()->json(),
            '213+ APKs migrated to campaigns[]'
        );
    }

    public function test_unbound_vend_still_gets_a_400(): void
    {
        Vend::forceCreate(['code' => 9902]);

        $this->getJson('/api/vends/9902/parameters/301')->assertStatus(400);
    }

    /**
     * A profile saved through the NEW write path (registry normalization)
     * must produce the same wire shape as the healed legacy row — i.e. the
     * normalization is idempotent across save generations.
     */
    public function test_freshly_saved_profile_produces_the_same_wire_shape(): void
    {
        $vend = Vend::forceCreate(['code' => 9903]);

        $setting = ApkSetting::create([
            'name' => 'Fresh profile',
            'settings_parameter_json' => [
                'enablePromoHeaderText' => 'true',
                'discountPercent01' => '5',
                'buy1free1X' => null,
            ],
        ]);

        DB::table('apk_setting_vend')->insert([
            'apk_setting_id' => $setting->id,
            'vend_id' => $vend->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = $this->getJson('/api/vends/9903/parameters/301')->assertOk()->json();

        $this->assertSame('true', $data['enablePromoHeaderText']);
        $this->assertSame(5, $data['discountPercent01']);
        $this->assertSame(-1, $data['buy1free1X']);
        $this->assertSame(
            array_keys(ApkSettingParameters::SCHEMA),
            array_values(array_intersect(array_keys($data), array_keys(ApkSettingParameters::SCHEMA))),
            'canonical key order drifted'
        );
    }
}
