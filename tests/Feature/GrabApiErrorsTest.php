<?php

namespace Tests\Feature;

use App\Models\DeliveryPlatformApiError;
use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\Operator;
use App\Models\User;
use App\Services\DeliveryPlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * Grab Errors (Brian, 2026-10-30): every failed exchange with Grab, both ways, kept 30
 * days, plus orders Grab has that never reached us.
 */
class GrabApiErrorsTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const MERCHANT = '4-C7W2RE5APADUGN';

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);
        Queue::fake();
    }

    private function store()
    {
        return $this->bindGrabStore(4754, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);
    }

    public function test_a_call_grab_refuses_is_kept_with_grabs_own_message(): void
    {
        $binding = $this->store();
        Http::fake(['*/merchant/pause' => Http::response(['reason' => 'internal_error', 'message' => 'Something broke'], 500)]);

        app(DeliveryPlatformService::class)->pauseStore($binding);

        $error = DeliveryPlatformApiError::sole();
        $this->assertSame('outgoing', $error->direction);
        $this->assertSame('store_pause', $error->action);
        $this->assertSame(500, $error->status);
        $this->assertSame(self::MERCHANT, $error->merchant_id);
        $this->assertSame($binding->delivery_platform_ref_number_id, $error->delivery_platform_ref_number_id);
        $this->assertSame($this->grabOperator->id, $error->delivery_platform_operator_id);
        $this->assertSame('internal_error — Something broke', $error->message);
        $this->assertTrue($error->request_json['isPause']);
    }

    public function test_no_answer_from_grab_is_kept_too(): void
    {
        $binding = $this->store();
        Http::fake(['*' => Http::failedConnection('cURL error 28: Operation timed out')]);

        try {
            app(DeliveryPlatformService::class)->pauseStore($binding);
        } catch (\Throwable) {
            // The caller's own handling is not what this test is about.
        }

        $error = DeliveryPlatformApiError::sole();
        $this->assertNull($error->status);
        $this->assertStringContainsString('timed out', $error->message);
    }

    public function test_pausing_a_store_grab_already_closed_is_not_an_error(): void
    {
        $binding = $this->store();
        Http::fake(['*/merchant/pause' => Http::response(['reason' => 'already_exists', 'target' => 'NotCoverOpeningHours'], 409)]);

        $this->assertNotNull(app(DeliveryPlatformService::class)->pauseStore($binding));
        $this->assertSame(0, DeliveryPlatformApiError::count());
    }

    public function test_secrets_and_other_hosts_are_never_kept(): void
    {
        Http::fake([
            'api.grab.com/*' => Http::response(['error' => 'invalid_client', 'error_description' => 'bad secret'], 401),
            'example.com/*' => Http::response([], 500),
        ]);

        Http::post('https://api.grab.com/grabid/v1/oauth2/token', ['client_id' => 'id', 'client_secret' => 'shh']);
        Http::post('https://example.com/anything', ['x' => 1]);

        $error = DeliveryPlatformApiError::sole();
        $this->assertSame('oauth_token', $error->action);
        $this->assertNull($error->request_json);
        $this->assertSame('invalid_client — bad secret', $error->message);
        $this->assertStringNotContainsString('shh', json_encode($error->toArray()));
    }

    public function test_an_update_for_an_order_we_never_stored_is_a_404_kept_with_what_grab_sent(): void
    {
        // 4754, 2026-10-11..19: its orders were refused, then each Grab update crashed (500).
        $this->store();

        $this->putJson('/api/delivery/grab/order/update', [
            'merchantID' => self::MERCHANT,
            'orderID' => '123-C8KCEV',
            'state' => 'CANCELLED',
            'receiver' => ['name' => 'A Customer', 'phones' => '91234567'],
        ])->assertStatus(404)->assertJsonPath('error_code', 404);

        $error = DeliveryPlatformApiError::sole();
        $this->assertSame('incoming', $error->direction);
        $this->assertSame('callback_order_update', $error->action);
        $this->assertSame('123-C8KCEV', $error->order_id);
        $this->assertSame('No order found for this merchant ID and order ID.', $error->message);
        $this->assertArrayNotHasKey('receiver', $error->request_json);
        $this->assertSame('CANCELLED', $error->request_json['state']);
    }

    public function test_a_refused_order_is_kept_with_the_reason(): void
    {
        $binding = $this->store();
        app(\App\Services\GrabStoreService::class)->pause($binding, null, 'Repair');
        Http::fake();

        $this->postJson('/api/delivery/grab/order/create', $this->grabOrderPayload(self::MERCHANT, 4754, [$this->grabItems['U-49']->id]))
            ->assertStatus(404);

        $error = DeliveryPlatformApiError::query()->where('direction', 'incoming')->sole();
        $this->assertSame('callback_order_create', $error->action);
        $this->assertSame('Vending Machine is paused for this Platform Ref ID.', $error->message);
        $this->assertSame($binding->delivery_platform_ref_number_id, $error->delivery_platform_ref_number_id);
    }

    public function test_a_crash_in_a_callback_is_kept_with_the_exception(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['api', 'grab.callback-errors'])
            ->post('/api/delivery/grab/order/create-crash-test', fn () => throw new \RuntimeException('Replicated Order ID on Creation'));

        $this->postJson('/api/delivery/grab/order/create-crash-test', ['merchantID' => self::MERCHANT, 'orderID' => 'X-1'])
            ->assertStatus(500);

        $error = DeliveryPlatformApiError::sole();
        $this->assertSame(500, $error->status);
        $this->assertSame('RuntimeException: Replicated Order ID on Creation', $error->message);
        $this->assertSame('X-1', $error->order_id);
    }

    public function test_a_webhook_auth_refusal_is_kept(): void
    {
        config(['grab.webhook_auth' => 'enforce']);

        $this->getJson('/api/delivery/grab/merchant/menu?merchantID='.self::MERCHANT.'&partnerMerchantID=4754')->assertStatus(401);

        $error = DeliveryPlatformApiError::sole();
        $this->assertSame('callback_menu', $error->action);
        $this->assertSame(401, $error->status);
        $this->assertSame(self::MERCHANT, $error->merchant_id);
    }

    public function test_orders_grab_has_that_never_reached_us_are_listed_once(): void
    {
        $binding = $this->store();
        $binding->forceFill(['start_date' => now()->subDays(5)])->save();
        DeliveryPlatformOrder::withoutGlobalScopes()->forceCreate([
            'delivery_platform_id' => $this->grabOperator->delivery_platform_id,
            'delivery_platform_operator_id' => $this->grabOperator->id,
            'order_id' => 'ORDER-WE-HAVE',
            'short_order_id' => 'GF-1',
            'order_created_at' => now()->subDay(),
            'platform_ref_id' => self::MERCHANT,
            'vend_code' => 4754,
            'delivery_product_mapping_vend_id' => $binding->id,
        ]);
        Http::fake(['*/partner/v1/orders*' => Http::response(['more' => false, 'orders' => [
            ['orderID' => 'ORDER-WE-HAVE', 'shortOrderNumber' => 'GF-1', 'orderState' => 'DELIVERED'],
            ['orderID' => 'ORDER-MISSING', 'shortOrderNumber' => 'GF-2', 'orderState' => 'CANCELLED', 'orderTime' => '2026-10-29T12:00:00+08:00', 'receiver' => ['name' => 'X']],
        ]], 200)]);

        $this->artisan('grab:missed-orders')->assertSuccessful();
        $this->artisan('grab:missed-orders')->assertSuccessful();

        $error = DeliveryPlatformApiError::sole();
        $this->assertSame('order_missed', $error->action);
        $this->assertSame('ORDER-MISSING', $error->order_id);
        $this->assertStringContainsString('GF-2 (CANCELLED', $error->message);
        $this->assertArrayNotHasKey('receiver', $error->response_json);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/partner/v1/orders?')
            && $request['merchantID'] === self::MERCHANT && $request['page'] === 1);
    }

    public function test_errors_older_than_30_days_are_deleted(): void
    {
        $old = DeliveryPlatformApiError::create(['direction' => 'outgoing', 'action' => 'store_pause', 'status' => 500]);
        $old->forceFill(['created_at' => now()->subDays(31)])->save();
        DeliveryPlatformApiError::create(['direction' => 'outgoing', 'action' => 'store_pause', 'status' => 500]);

        $this->artisan('grab:prune-api-errors')->assertSuccessful();

        $this->assertSame(1, DeliveryPlatformApiError::count());
        $this->assertNull(DeliveryPlatformApiError::find($old->id));
    }

    public function test_the_page_shows_a_client_only_its_own_stores_errors(): void
    {
        $binding = $this->store();
        Http::fake(['*/merchant/pause' => Http::response(['reason' => 'boom'], 500)]);
        app(DeliveryPlatformService::class)->pauseStore($binding);
        DeliveryPlatformApiError::create(['direction' => 'outgoing', 'action' => 'oauth_token', 'status' => 401, 'message' => 'no store']);

        Permission::findOrCreate('read delivery-platform-vends', 'web');
        $hipl = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $hipl->givePermissionTo('read delivery-platform-vends');
        $this->actingAs($hipl)->get('/grab-errors')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('GrabError/Index')->has('errors.data', 2)
                ->where('errors.data.1.vend_code', 4754));

        $client = Operator::forceCreate([
            'name' => 'Kumoyo', 'code' => 'KMY', 'country_id' => $this->grabMapping->operator->country_id, 'created_by' => 1, 'gst_vat_rate' => 9,
        ]);
        $clientUser = User::factory()->create(['operator_id' => $client->id]);
        $clientUser->givePermissionTo('read delivery-platform-vends');
        $this->actingAs($clientUser)->get('/grab-errors')
            ->assertInertia(fn ($page) => $page->has('errors.data', 0));

        // And the Grab Stores row counts it, with the details in the store's history.
        $this->actingAs($hipl)->get('/grab-stores')
            ->assertInertia(fn ($page) => $page->where('stores.data.0.errors_7d', 1));
        $ref = DeliveryPlatformRefNumber::query()->where('ref_number', self::MERCHANT)->first();
        $this->actingAs($hipl)->getJson("/grab-stores/{$ref->id}/history")
            ->assertJsonPath('errors.0.action', 'store_pause')
            ->assertJsonPath('errors.0.message', 'boom');
    }
}
