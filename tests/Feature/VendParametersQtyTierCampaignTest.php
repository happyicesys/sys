<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/vends/{code}/parameters/{apkver?} with QtyTier campaigns bound.
 *
 * QtyTier campaigns are delivered to EVERY deployed APK version through the
 * legacy flat tier keys (enableBundleDiscount, bundleStartDate/EndDate,
 * enableDiscount01..03, discountPercent01..03) — never through campaigns[],
 * whose label engine doesn't know the type. Profiles with no QtyTier
 * campaign keep shipping their hand-set legacy values untouched.
 */
class VendParametersQtyTierCampaignTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Vend 9910 bound to a profile whose blob carries hand-set legacy tier
     * values — the deprecated-but-functional authoring path.
     */
    private function makeVendWithHandSetTiers(): int
    {
        $vend = Vend::forceCreate(['code' => 9910]);

        $settingId = DB::table('apk_settings')->insertGetId([
            'name' => 'Hand-set tiers profile',
            'settings_parameter_json' => json_encode([
                'enableBundleDiscount' => 'true',
                'bundleStartDate' => '2025-01-01',
                'bundleEndDate' => '2025-12-31',
                'enableDiscount01' => 'true',
                'discountPercent01' => 7,
                'enableDiscount02' => 'false',
                'discountPercent02' => 1,
                'enableDiscount03' => 'false',
                'discountPercent03' => 1,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('apk_setting_vend')->insert([
            'apk_setting_id' => $settingId,
            'vend_id' => $vend->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $settingId;
    }

    /**
     * Plain create works: VisibleTo is a query scope, not a global one, and
     * operator_id is nullable — API-path reads are unscoped.
     */
    private function bindCampaign(int $settingId, array $attributes): Campaign
    {
        $campaign = Campaign::create([
            'name' => $attributes['name'] ?? 'Qty tier campaign',
            'is_active' => $attributes['is_active'] ?? true,
            'promo_type' => $attributes['promo_type'] ?? Campaign::TYPE_QTY_TIER,
            'bundle_qty' => $attributes['bundle_qty'] ?? null,
            'value' => $attributes['value'] ?? null,
            'start_at' => $attributes['start_at'] ?? null,
            'end_at' => $attributes['end_at'] ?? null,
        ]);

        DB::table('apk_setting_campaign')->insert([
            'apk_setting_id' => $settingId,
            'campaign_id' => $campaign->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $campaign;
    }

    public function test_hand_set_tier_values_pass_through_when_no_qty_tier_campaign(): void
    {
        $this->makeVendWithHandSetTiers();

        $data = $this->getJson('/api/vends/9910/parameters/301')
            ->assertOk()
            ->json();

        // The deprecated authoring path stays functional: stored values reach
        // the wire exactly.
        $this->assertSame('true', $data['enableBundleDiscount']);
        $this->assertSame('2025-01-01', $data['bundleStartDate']);
        $this->assertSame('2025-12-31', $data['bundleEndDate']);
        $this->assertSame('true', $data['enableDiscount01']);
        $this->assertSame(7, $data['discountPercent01']);
        $this->assertSame('false', $data['enableDiscount02']);
        $this->assertSame(1, $data['discountPercent02']);
        $this->assertSame('false', $data['enableDiscount03']);
        $this->assertSame(1, $data['discountPercent03']);
    }

    public function test_bound_qty_tier_campaign_overrides_the_legacy_keys_and_stays_out_of_campaigns(): void
    {
        $settingId = $this->makeVendWithHandSetTiers();

        $tier = $this->bindCampaign($settingId, [
            'bundle_qty' => 3,
            'value' => 15,
            'start_at' => '2026-08-01 00:00:00',
            'end_at' => '2026-09-30 00:00:00',
        ]);

        // A normal campaign next to it must keep flowing through campaigns[].
        $percentage = $this->bindCampaign($settingId, [
            'name' => 'Plain percentage',
            'promo_type' => Campaign::TYPE_PERCENTAGE,
            'value' => 10,
        ]);

        $data = $this->getJson('/api/vends/9910/parameters/301')
            ->assertOk()
            ->json();

        // bundle_qty 3 → slot 02; the other slots are disabled, hand-set
        // values overridden.
        $this->assertSame('true', $data['enableBundleDiscount']);
        $this->assertSame('true', $data['enableDiscount02']);
        $this->assertSame(15, $data['discountPercent02']);
        $this->assertSame('false', $data['enableDiscount01']);
        $this->assertSame(1, $data['discountPercent01']);
        $this->assertSame('false', $data['enableDiscount03']);
        $this->assertSame(1, $data['discountPercent03']);
        $this->assertSame('2026-08-01', $data['bundleStartDate']);
        $this->assertSame('2026-09-30', $data['bundleEndDate']);

        // The QtyTier campaign never rides in campaigns[]; other types do.
        $campaignIds = collect($data['campaigns'])->pluck('id');
        $this->assertNotContains($tier->id, $campaignIds);
        $this->assertContains($percentage->id, $campaignIds);
    }

    public function test_inactive_qty_tier_campaign_is_ignored(): void
    {
        $settingId = $this->makeVendWithHandSetTiers();

        $this->bindCampaign($settingId, [
            'is_active' => false,
            'bundle_qty' => 3,
            'value' => 15,
        ]);

        $data = $this->getJson('/api/vends/9910/parameters/301')
            ->assertOk()
            ->json();

        // Passthrough: the hand-set values survive untouched.
        $this->assertSame('true', $data['enableDiscount01']);
        $this->assertSame(7, $data['discountPercent01']);
        $this->assertSame('false', $data['enableDiscount02']);
        $this->assertSame(1, $data['discountPercent02']);
        $this->assertSame('2025-01-01', $data['bundleStartDate']);
        $this->assertSame('2025-12-31', $data['bundleEndDate']);
        $this->assertSame([], $data['campaigns']);
    }
}
