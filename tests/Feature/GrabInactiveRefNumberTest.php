<?php

namespace Tests\Feature;

use App\Jobs\NotifyDeliveryPlatformUpdateMenu;
use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use App\Services\DeliveryPlatformService;
use App\Services\DeliveryProductMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * Inactive on the Delivery Platform Ref Numbers page switches a Grab store off on its
 * own (Brian, 2026-10-29), with the binding still open and on: no menu, no orders, no
 * notify, and kept closed on Grab by the pause re-assert.
 */
class GrabInactiveRefNumberTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const MERCHANT = '4-C2B3JJAWN2LHMA';

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);
    }

    private function inactiveStore(int $vendCode = 2870, string $merchant = self::MERCHANT): DeliveryProductMappingVend
    {
        $store = $this->bindGrabStore($vendCode, $merchant, [11 => ['qty' => 5, 'capacity' => 10]]);
        DeliveryPlatformRefNumber::where('ref_number', $merchant)->update(['status' => DeliveryPlatformRefNumber::STATUS_INACTIVE]);

        return $store->fresh();
    }

    public function test_an_inactive_ref_number_stops_selling_with_the_binding_still_on(): void
    {
        $store = $this->inactiveStore();

        $this->assertTrue((bool) $store->is_active);
        $this->assertNull($store->end_date);
        $this->assertFalse($store->isSelling());
        $this->assertSame(0, DeliveryProductMappingVend::query()->selling()->count());
    }

    public function test_grab_gets_a_4xx_for_its_menu_and_its_orders(): void
    {
        Queue::fake();
        $this->inactiveStore();

        $this->getJson('/api/delivery/grab/merchant/menu?merchantID='.self::MERCHANT.'&partnerMerchantID=2870')
            ->assertStatus(404)
            ->assertJsonPath('error_message', 'Vending Machine is paused for this Platform Ref ID.');

        $this->postJson('/api/delivery/grab/order/create', $this->grabOrderPayload(self::MERCHANT, 2870, [$this->grabItems['U-49']->id]))
            ->assertStatus(404);
        $this->assertSame(0, DeliveryPlatformOrder::count());
    }

    public function test_grab_is_never_told_to_pull_its_menu(): void
    {
        $store = $this->inactiveStore();
        Queue::fake();

        app(DeliveryProductMappingService::class)->syncVendChannels(null, $store->vend_id);
        $this->artisan('grab:sync-all-menu')->assertSuccessful();
        Queue::assertNotPushed(NotifyDeliveryPlatformUpdateMenu::class);

        // A notify queued before the ref went Inactive is dropped when it runs.
        Http::fake();
        (new NotifyDeliveryPlatformUpdateMenu($store, true))->handle(app(DeliveryPlatformService::class));
        Http::assertNothingSent();
    }

    public function test_the_pause_reassert_keeps_grab_from_reopening_it(): void
    {
        // 2871 on 2026-10-29: ref Inactive, Grab still listing the store.
        $this->inactiveStore();
        Http::fake([
            '*/store/status' => Http::response(['isOpen' => true], 200),
            '*/partner/v1/merchant/pause' => Http::response(null, 204),
        ]);

        $this->artisan('delivery:sync-store-pause --apply --only-if-open')->assertSuccessful();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/merchant/pause')
            && $request['merchantID'] === self::MERCHANT && $request['isPause'] === true);
    }

    public function test_a_store_with_no_ref_number_linked_still_sells(): void
    {
        $store = $this->bindGrabStore(4416, '4-NOREF', [11 => ['qty' => 5, 'capacity' => 10]]);
        $store->forceFill(['delivery_platform_ref_number_id' => null])->save();

        $this->assertTrue($store->fresh()->isSelling());
        $this->getJson('/api/delivery/grab/merchant/menu?merchantID=4-NOREF&partnerMerchantID=4416')->assertOk();
    }

    public function test_an_active_ref_number_on_another_merchant_is_untouched(): void
    {
        $this->inactiveStore();
        $this->bindGrabStore(4416, '4-LIVE', [11 => ['qty' => 5, 'capacity' => 10]]);

        $this->assertSame(['4-LIVE'], DeliveryProductMappingVend::query()->selling()->pluck('platform_ref_id')->all());
        $this->getJson('/api/delivery/grab/merchant/menu?merchantID=4-LIVE&partnerMerchantID=4416')->assertOk();
    }
}
