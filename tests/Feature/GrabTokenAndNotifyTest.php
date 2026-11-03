<?php

namespace Tests\Feature;

use App\Jobs\NotifyDeliveryPlatformUpdateMenu;
use App\Models\DeliveryPlatformApiError;
use App\Models\DeliveryProductMappingVend;
use App\Services\DeliveryPlatformService;
use App\Services\Grab\GrabTokenKeeper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * Grab errors 2026-10-30..11-01 and what answers them: tokens renewed before they lapse
 * (DCVIC lapsed 10-29, LEA 10-31) and once on a 401; a "sync menu too frequently, retry
 * after N seconds" notify asked again after N; a store Grab says is not integrated left
 * alone for a day; the nightly full sync spaced instead of 63 calls at 00:00 (429s).
 */
class GrabTokenAndNotifyTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const STORE = '4-C73WV2JKAFCABE';

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        Queue::fake();
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);
    }

    private function token(): string
    {
        return (string) $this->grabOperator->externalOauthToken()->value('access_token');
    }

    public function test_a_token_with_no_known_expiry_is_renewed_and_its_real_expiry_kept(): void
    {
        Http::fake(['*/grabid/v1/oauth2/token' => Http::response(['access_token' => 'fresh', 'token_type' => 'Bearer', 'expires_in' => 604799], 200)]);
        $keeper = app(GrabTokenKeeper::class);

        $this->assertSame([$this->grabOperator->id => 'renewed'], $keeper->renewDue());
        $this->assertSame('fresh', $this->token());
        $expires = $keeper->expiresAt($this->grabOperator->fresh());
        $this->assertTrue($expires->between(now()->addDays(6)->addHours(23), now()->addDays(7)->addMinute()));

        // Valid for days: left alone.
        $this->assertSame([$this->grabOperator->id => 'valid'], $keeper->renewDue());
        Http::assertSentCount(1);
    }

    public function test_a_failed_renewal_waits_before_asking_again(): void
    {
        Http::fake(['*/grabid/v1/oauth2/token' => Http::response(['error' => 'invalid_client'], 401)]);
        $keeper = app(GrabTokenKeeper::class);

        $this->assertSame([$this->grabOperator->id => 'failed'], $keeper->renewDue());
        $this->assertSame([$this->grabOperator->id => 'backoff'], $keeper->renewDue());
        $this->assertSame(0, Artisan::call('grab:renew-tokens'), 'waiting out the backoff is not a new failure');
        Http::assertSentCount(1);

        // --force asks at once, and the command reports the failure.
        $this->assertSame(1, Artisan::call('grab:renew-tokens', ['--force' => true]));
        Http::assertSentCount(2);
    }

    public function test_a_call_answered_401_renews_the_token_once_and_is_asked_again(): void
    {
        $this->bindGrabStore(4754, self::STORE, [11 => ['qty' => 5, 'capacity' => 10]]);
        Http::fake([
            '*/grabid/v1/oauth2/token' => Http::response(['access_token' => 'fresh', 'token_type' => 'Bearer', 'expires_in' => 604799], 200),
            '*/store/status' => fn (Request $request) => $request->header('Authorization')[0] === 'Bearer fresh'
                ? Http::response(['isOpen' => true], 200)
                : Http::response(null, 401),
        ]);

        $status = app(DeliveryPlatformService::class)->storeStatus(DeliveryProductMappingVend::query()->withoutGlobalScopes()->sole());

        $this->assertTrue($status['success']);
        $this->assertSame('fresh', $this->token());
    }

    public function test_a_notify_grab_calls_too_frequent_is_asked_again_after_the_wait_it_names(): void
    {
        $store = $this->bindGrabStore(4754, self::STORE, [11 => ['qty' => 5, 'capacity' => 10]]);
        Http::fake(['*/partner/v1/merchant/menu/notification' => Http::response(['reason' => 'conflict', 'message' => 'sync menu too frequently, retry after 310 seconds'], 409)]);

        (new NotifyDeliveryPlatformUpdateMenu($store, true))->handle(app(DeliveryPlatformService::class));

        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class, function ($job) {
            $retries = (fn () => $this->retries)->call($job);

            return $retries === 1 && $job->delay->diffInSeconds(now(), true) >= 320;
        });
        $this->assertSame(310, NotifyDeliveryPlatformUpdateMenu::retryAfterSeconds(['data' => ['message' => 'sync menu too frequently, retry after 310 seconds']]));
    }

    public function test_a_notify_soon_after_the_last_one_waits_instead_of_being_refused(): void
    {
        $store = $this->bindGrabStore(4754, self::STORE, [11 => ['qty' => 5, 'capacity' => 10]]);
        Http::fake(['*/partner/v1/merchant/menu/notification' => Http::response([], 200)]);
        $notify = fn () => (new NotifyDeliveryPlatformUpdateMenu($store, true))->handle(app(DeliveryPlatformService::class));

        $notify();
        Http::assertSentCount(1);
        Queue::assertNotPushed(NotifyDeliveryPlatformUpdateMenu::class);

        // 200 s later: inside Grab's lock, so nothing is sent and the job comes back when it ends.
        $this->travel(200)->seconds();
        $notify();
        Http::assertSentCount(1);
        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class, function ($job) {
            $retries = (fn () => $this->retries)->call($job);
            $force = (fn () => $this->force)->call($job);

            return $retries === 0 && $force && abs($job->delay->diffInSeconds(now(), true) - 125) <= 2;
        });

        $this->travel(126)->seconds();
        $notify();
        Http::assertSentCount(2);
    }

    public function test_a_notify_grab_refused_does_not_start_the_wait(): void
    {
        $store = $this->bindGrabStore(4754, self::STORE, [11 => ['qty' => 5, 'capacity' => 10]]);
        Http::fake(['*/partner/v1/merchant/menu/notification' => Http::response(['reason' => 'conflict', 'message' => 'sync menu too frequently, retry after 310 seconds'], 409)]);
        $notify = fn () => (new NotifyDeliveryPlatformUpdateMenu($store, true))->handle(app(DeliveryPlatformService::class));

        $notify();
        $notify();

        Http::assertSentCount(2);
    }

    public function test_a_store_grab_says_is_not_integrated_is_not_notified_for_a_day(): void
    {
        $store = $this->bindGrabStore(4754, self::STORE, [11 => ['qty' => 5, 'capacity' => 10]]);
        DeliveryPlatformApiError::create([
            'direction' => DeliveryPlatformApiError::DIRECTION_OUTGOING, 'action' => 'menu_notify', 'status' => 403,
            'merchant_id' => self::STORE, 'message' => 'forbidden — merchant not be integrated yet',
        ]);
        Http::fake();

        (new NotifyDeliveryPlatformUpdateMenu($store, true))->handle(app(DeliveryPlatformService::class));

        Http::assertNothingSent();
    }

    public function test_the_nightly_full_sync_is_spaced_out(): void
    {
        config(['grab.daily_sync_spacing_seconds' => 3]);
        $this->bindGrabStore(4754, self::STORE, [11 => ['qty' => 5, 'capacity' => 10]]);
        $this->bindGrabStore(4755, '4-C7BBBBBBBBBBBB', [11 => ['qty' => 5, 'capacity' => 10]]);
        $this->bindGrabStore(4756, '4-C7CCCCCCCCCCCC', [11 => ['qty' => 5, 'capacity' => 10]]);
        Queue::fake();

        Artisan::call('grab:sync-all-menu');

        $delays = [];
        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class, function ($job) use (&$delays) {
            $delays[] = (int) round(now()->diffInSeconds($job->delay, true));

            return true;
        });
        sort($delays);
        $this->assertSame([0, 3, 6], $delays);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
