<?php

namespace Tests\Feature;

use App\Jobs\NotifyDeliveryPlatformUpdateMenu;
use App\Models\DeliveryProductMappingVend;
use App\Services\DeliveryPlatformService;
use App\Services\DeliveryProductMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * The menu Grab pulls, and when Grab is told to pull it. GRAB_REVAMP_PLAN_2026-10-29.md
 * C1 (a full re-sync on every CHANNEL frame), C3 (paused stores answered with text),
 * A4 (a moved store's menu pull failed).
 */
class GrabMenuTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const MERCHANT = '4-C6VGNELVVPWKAX';

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off']);
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);
        $this->grabItem('U-26', 12, 500, photo: null);
    }

    private function store(bool $isActive = true): DeliveryProductMappingVend
    {
        return $this->bindGrabStore(4416, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10], 12 => ['qty' => 0, 'capacity' => 10]], $isActive);
    }

    public function test_the_menu_carries_integer_cent_prices_and_stock(): void
    {
        $this->store();

        $menu = $this->getJson('/api/delivery/grab/merchant/menu?merchantID='.self::MERCHANT.'&partnerMerchantID=4416')
            ->assertOk()
            ->json();

        $this->assertSame(self::MERCHANT, $menu['merchantID']);
        $this->assertSame('4416', $menu['partnerMerchantID']);
        $this->assertSame(['code' => 'SGD', 'symbol' => 'S$', 'exponent' => 2], $menu['currency']);

        $items = collect($menu['categories'][0]['subCategories'][0]['items'])->keyBy('name');
        $this->assertSame(280, $items['Product U-49']['price']);
        $this->assertSame('AVAILABLE', $items['Product U-49']['availableStatus']);
        $this->assertSame(5, $items['Product U-49']['maxStock']);
        $this->assertSame(['https://example.test/p.jpg'], $items['Product U-49']['photos']);

        // Sold out; and no photo no longer fails the whole store's menu.
        $this->assertSame('UNAVAILABLE', $items['Product U-26']['availableStatus']);
        $this->assertSame(0, $items['Product U-26']['maxStock']);
        $this->assertSame([], $items['Product U-26']['photos']);
    }

    public function test_a_moved_store_gets_its_menu_by_merchant(): void
    {
        $this->store();

        $this->getJson('/api/delivery/grab/merchant/menu?merchantID='.self::MERCHANT.'&partnerMerchantID=2114')
            ->assertOk()
            ->assertJsonPath('partnerMerchantID', '4416');
    }

    public function test_a_paused_store_answers_json_not_text(): void
    {
        $this->store(isActive: false);

        $this->getJson('/api/delivery/grab/merchant/menu?merchantID='.self::MERCHANT.'&partnerMerchantID=4416')
            ->assertStatus(404)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error_message', 'Vending Machine is paused for this Platform Ref ID.');
    }

    public function test_a_channel_frame_queues_one_delayed_notify_per_selling_store(): void
    {
        $selling = $this->store();
        Queue::fake();

        app(DeliveryProductMappingService::class)->syncVendChannels(null, $selling->vend_id);
        app(DeliveryProductMappingService::class)->syncVendChannels(null, $selling->vend_id);

        // Unique per store while it waits: the second frame rides along.
        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class, 1);
        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class, fn ($job) => $job->delay !== null);
    }

    public function test_a_paused_store_is_never_notified(): void
    {
        $paused = $this->store(isActive: false);
        Queue::fake();

        app(DeliveryProductMappingService::class)->syncVendChannels(null, $paused->vend_id);

        Queue::assertNotPushed(NotifyDeliveryPlatformUpdateMenu::class);
    }

    public function test_the_notify_is_skipped_when_grab_already_has_this_menu(): void
    {
        $store = $this->store();
        Http::fake(['*/partner/v1/merchant/menu/notification' => Http::response(null, 204)]);

        $menu = app(DeliveryPlatformService::class)->buildMenu($store);
        // As MySQL hands it back: keys reordered, and yesterday's startTime.
        $menu['sellingTimes'][0]['startTime'] = '2020-01-01 16:00:00';
        $store->forceFill(['last_menu_json' => array_reverse($menu, true)])->save();

        (new NotifyDeliveryPlatformUpdateMenu($store))->handle(app(DeliveryPlatformService::class));
        Http::assertNothingSent();

        // A real change (a sale moved the stock) is sent.
        $store->vend->vendChannels()->where('code', 11)->update(['qty' => 4]);
        (new NotifyDeliveryPlatformUpdateMenu($store))->handle(app(DeliveryPlatformService::class));
        Http::assertSentCount(1);
    }

    public function test_the_daily_sync_forces_selling_stores_only(): void
    {
        $this->store();
        $this->bindGrabStore(4754, '4-PAUSED', [11 => ['qty' => 1, 'capacity' => 10]], isActive: false);
        Queue::fake();

        $this->artisan('grab:sync-all-menu')->assertSuccessful();

        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class, 1);
        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class, fn ($job) => $job->uniqueId() === DeliveryProductMappingVend::where('vend_code', 4416)->value('id').':force');
    }

    public function test_menu_diff_reports_a_store_whose_menu_grab_already_has(): void
    {
        $store = $this->store();
        $store->forceFill(['last_menu_json' => app(DeliveryPlatformService::class)->buildMenu($store)])->save();

        $this->artisan('grab:menu-diff')
            ->expectsOutputToContain('same')
            ->assertSuccessful();
    }
}
