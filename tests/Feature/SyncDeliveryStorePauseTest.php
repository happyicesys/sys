<?php

namespace Tests\Feature;

use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingVend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * delivery:sync-store-pause re-asserts a pause that Grab expires after 24 hours.
 *
 * THE TRAP. Grab's pause endpoint takes a MERCHANT id, but mark1's pause state
 * lives on a mapping ROW - and a merchant is reused across vends when a listing
 * moves machine. On 2026-09-23 merchant 4-C7CDNZDXKEBJPE held a stale paused row
 * on vend 2658 and a live one on vend 2873; 4-C7CCRTEJJYUJR6 had run
 * 2629 -> 2734 -> 2114 -> 2401. Pausing on the stale row would have taken the
 * live machine off Grab for 24 hours - an outage caused by the tool meant to
 * prevent one. Caught in review before the first --apply ever ran.
 *
 * So the rule is the merchant, not the row: one active mapping anywhere means
 * the merchant is selling and is never paused from here.
 */
class SyncDeliveryStorePauseTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryProductMapping $mapping;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = DeliveryPlatform::forceCreate([
            'country_id' => 1,
            'name' => 'Grab',
            'slug' => 'grab',
        ]);

        // The command defaults to --type=production.
        $operator = DeliveryPlatformOperator::forceCreate([
            'delivery_platform_id' => $platform->id,
            'operator_id' => 1,
            'type' => 'production',
        ]);

        $operator->externalOauthToken()->create([
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'access_token' => 'test-token',
            'scopes' => 'mart.partner_api',
        ]);

        $this->mapping = DeliveryProductMapping::forceCreate([
            'delivery_platform_operator_id' => $operator->id,
            'operator_id' => 1,
            'product_mapping_id' => 1,
            'name' => 'test mapping',
        ]);

        Http::fake([
            '*/partner/v1/merchant/pause' => Http::response(['success' => true], 200),
        ]);
    }

    private function mappingVend(string $merchantId, int $vendCode, bool $isActive): DeliveryProductMappingVend
    {
        return DeliveryProductMappingVend::forceCreate([
            'delivery_product_mapping_id' => $this->mapping->id,
            'vend_id' => $vendCode,
            'vend_code' => $vendCode,
            'platform_ref_id' => $merchantId,
            'is_active' => $isActive,
        ]);
    }

    public function test_a_merchant_still_live_on_another_vend_is_never_paused(): void
    {
        // The 2658 / 2873 shape: same merchant, stale paused row plus a live one.
        $this->mappingVend('4-MOVED', 2658, false);
        $this->mappingVend('4-MOVED', 2873, true);

        $this->artisan('delivery:sync-store-pause --apply')
            ->expectsOutputToContain('SKIPPED: merchant is live on another vend')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_merchant_paused_everywhere_is_paused(): void
    {
        $this->mappingVend('4-RETIRED', 4754, false);

        $this->artisan('delivery:sync-store-pause --apply')->assertSuccessful();

        Http::assertSent(function ($request) {
            $body = $request->data();

            $this->assertSame('4-RETIRED', $body['merchantID']);
            $this->assertTrue($body['isPause']);

            return true;
        });
    }

    public function test_dry_run_sends_nothing(): void
    {
        $this->mappingVend('4-RETIRED', 4754, false);

        $this->artisan('delivery:sync-store-pause')
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        Http::assertNothingSent();
    }
}
