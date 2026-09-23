<?php

namespace Tests\Feature;

use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingVend;
use App\Services\DeliveryPlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * DeliveryPlatformService::pauseStore() wrapped its three arguments in a single
 * array, so Grab::pauseStore() took the whole array as $merchantID and the PUT
 * went out with merchantID as a nested object. Grab rejected every call, and the
 * failure was swallowed by a bare `if ($response['success'])` - no log, no error,
 * and the UI redirected as though the pause had worked.
 *
 * Live consequence: vend 4754 was paused on 2026-09-05 and kept taking Grab
 * orders anyway. Each order hit an uncaught exception, returned 500, and Grab
 * read that as "server broke, retry" - 15 retries per order, ~10 customer orders
 * lost over 18 days, entirely silently.
 *
 * The bug was only ever visible in the request body, so that is what these pin.
 */
class GrabPauseStoreArgumentsTest extends TestCase
{
    use RefreshDatabase;

    private const MERCHANT_ID = '4-TESTMERCHANT';

    private function makeMappingVend(): DeliveryProductMappingVend
    {
        $platform = DeliveryPlatform::forceCreate([
            'country_id' => 1,
            'name' => 'Grab',
            'slug' => 'grab',
        ]);

        $operator = DeliveryPlatformOperator::forceCreate([
            'delivery_platform_id' => $platform->id,
            'operator_id' => 1,
            'type' => 'sandbox',
        ]);

        // getHeaders() and getPartnerEndpoint() both read this, and
        // verifyOauthAccessToken() throws without an access_token.
        $operator->externalOauthToken()->create([
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'access_token' => 'test-token',
            'scopes' => 'mart.partner_api',
        ]);

        $mapping = DeliveryProductMapping::forceCreate([
            'delivery_platform_operator_id' => $operator->id,
            'operator_id' => 1,
            'product_mapping_id' => 1,
            'name' => 'test mapping',
        ]);

        return DeliveryProductMappingVend::forceCreate([
            'delivery_product_mapping_id' => $mapping->id,
            'vend_id' => 1,
            'vend_code' => 4754,
            'platform_ref_id' => self::MERCHANT_ID,
            'is_active' => false,
        ]);
    }

    private function fakeGrab(int $status = 200): void
    {
        Http::fake([
            '*/partner/v1/merchant/pause' => Http::response(['success' => true], $status),
        ]);
    }

    public function test_pause_sends_merchant_id_as_a_plain_string(): void
    {
        $this->fakeGrab();

        app(DeliveryPlatformService::class)->pauseStore($this->makeMappingVend());

        Http::assertSent(function ($request) {
            $body = $request->data();

            // The regression: this was an array, so Grab saw
            // merchantID: {merchantID: "...", 0: true, 1: "24h"}
            $this->assertIsString($body['merchantID']);
            $this->assertSame(self::MERCHANT_ID, $body['merchantID']);
            $this->assertTrue($body['isPause']);
            $this->assertSame('24h', $body['duration']);

            return true;
        });
    }

    public function test_resume_sends_is_pause_false(): void
    {
        $this->fakeGrab();

        app(DeliveryPlatformService::class)->pauseStore($this->makeMappingVend(), false);

        Http::assertSent(function ($request) {
            $body = $request->data();

            $this->assertSame(self::MERCHANT_ID, $body['merchantID']);
            $this->assertFalse($body['isPause']);

            return true;
        });
    }

    public function test_an_empty_success_body_is_not_reported_as_failure(): void
    {
        // Grab answers a successful pause with an empty body. pauseStore used to
        // return $response['data'] - null - so 4754's first real pause on
        // 2026-09-23 succeeded and was reported as FAILED.
        Http::fake([
            '*/partner/v1/merchant/pause' => Http::response('', 200),
        ]);

        $result = app(DeliveryPlatformService::class)->pauseStore($this->makeMappingVend());

        $this->assertNotNull($result, 'A 200 with an empty body must not read as failure');
        $this->assertTrue($result['success']);
    }

    public function test_a_rejected_pause_is_logged_rather_than_swallowed(): void
    {
        $this->fakeGrab(400);

        Log::spy();

        $result = app(DeliveryPlatformService::class)->pauseStore($this->makeMappingVend());

        $this->assertNull($result);

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($message, $context = []) => $message === 'Grab pauseStore failed'
                && ($context['merchant_id'] ?? null) === self::MERCHANT_ID
                && ($context['code'] ?? null) === 400)
            ->once();
    }
}
