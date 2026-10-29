<?php

namespace Tests\Feature;

use App\Http\Controllers\DeliveryPlatformCampaignController;
use App\Models\DeliveryPlatformCampaign;
use App\Models\DeliveryPlatformCampaignItem;
use App\Models\DeliveryPlatformCampaignItemVend;
use App\Models\DeliveryPlatformMenuRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * Campaign deletes that end the campaign on Grab, the scheduled pause re-assert that
 * asks Grab first, and menu-record pruning. GRAB_REVAMP_PLAN_2026-10-29.md D2, A3, C8.
 */
class GrabCampaignAndUpkeepTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.store_api_spacing_ms' => 0]);
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);
    }

    private function campaign(array $itemVends): DeliveryPlatformCampaign
    {
        $mappingVend = $this->bindGrabStore(4416, '4-STORE', [11 => ['qty' => 5, 'capacity' => 10]]);

        $campaign = DeliveryPlatformCampaign::forceCreate([
            'delivery_platform_operator_id' => $this->grabOperator->id,
            'delivery_product_mapping_id' => $this->grabMapping->id,
            'name' => 'test campaign',
        ]);
        $item = DeliveryPlatformCampaignItem::forceCreate(['delivery_platform_campaign_id' => $campaign->id, 'settings_json' => []]);

        foreach ($itemVends as $attributes) {
            DeliveryPlatformCampaignItemVend::forceCreate($attributes + [
                'delivery_platform_campaign_id' => $campaign->id,
                'delivery_platform_campaign_item_id' => $item->id,
                'delivery_product_mapping_vend_id' => $mappingVend->id,
                'vend_code' => 4416,
                'settings_json' => [],
            ]);
        }

        return $campaign;
    }

    public function test_deleting_a_campaign_ends_its_live_grab_campaigns_only(): void
    {
        Http::fake(['*/partner/v1/campaigns/*' => Http::response(null, 204)]);
        $campaign = $this->campaign([
            ['is_submitted' => true, 'platform_ref_id' => 'LIVE-1', 'datetime_to' => now()->addWeek()],
            ['is_submitted' => true, 'platform_ref_id' => 'ENDED-1', 'datetime_to' => now()->subDay()],
            ['is_submitted' => false, 'platform_ref_id' => null, 'datetime_to' => now()->addWeek()],
        ]);

        app(DeliveryPlatformCampaignController::class)->destroy($campaign->id);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/partner/v1/campaigns/LIVE-1'));
        $this->assertFalse((bool) DeliveryPlatformCampaignItemVend::where('platform_ref_id', 'LIVE-1')->value('is_active'));
        $this->assertNull(DeliveryPlatformCampaign::find($campaign->id));
    }

    public function test_deleting_a_campaign_item_ends_it_on_grab_before_the_rows_go(): void
    {
        Http::fake(['*/partner/v1/campaigns/*' => Http::response(null, 204)]);
        $campaign = $this->campaign([['is_submitted' => true, 'platform_ref_id' => 'LIVE-2', 'datetime_to' => now()->addWeek()]]);

        app(DeliveryPlatformCampaignController::class)->deleteItem($campaign->deliveryPlatformCampaignItems()->value('id'));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/partner/v1/campaigns/LIVE-2'));
        $this->assertSame(0, DeliveryPlatformCampaignItemVend::count());
    }

    public function test_the_pause_reassert_leaves_a_store_grab_still_holds_closed(): void
    {
        $this->bindGrabStore(4754, '4-PAUSED', [11 => ['qty' => 5, 'capacity' => 10]], isActive: false);
        Http::fake([
            '*/store/status' => Http::response(['isOpen' => false, 'closeReason' => 'mex_paused'], 200),
            '*/partner/v1/merchant/pause' => Http::response(null, 204),
        ]);

        $this->artisan('delivery:sync-store-pause --apply --only-if-open')
            ->expectsOutputToContain('already closed on Grab')
            ->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/merchant/pause'));
    }

    public function test_the_pause_reassert_repauses_a_store_grab_reopened(): void
    {
        $this->bindGrabStore(4754, '4-PAUSED', [11 => ['qty' => 5, 'capacity' => 10]], isActive: false);
        Http::fake([
            '*/store/status' => Http::response(['isOpen' => true], 200),
            '*/partner/v1/merchant/pause' => Http::response(null, 204),
        ]);

        $this->artisan('delivery:sync-store-pause --apply --only-if-open')->assertSuccessful();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/merchant/pause')
            && $request['merchantID'] === '4-PAUSED' && $request['isPause'] === true);
    }

    public function test_a_store_grab_refuses_is_not_paused_and_not_asked_again_for_a_day(): void
    {
        // DCVIC's 11 stores on the first prod run (2026-10-29): 403 on status and pause.
        $this->bindGrabStore(4609, '4-REFUSED', [11 => ['qty' => 5, 'capacity' => 10]], isActive: false);
        Http::fake([
            '*/store/status' => Http::response(['reason' => 'forbidden'], 403),
            '*/partner/v1/merchant/pause' => Http::response(null, 204),
        ]);

        $this->artisan('delivery:sync-store-pause --apply --only-if-open')
            ->expectsOutputToContain('Grab answers 403')
            ->assertSuccessful();
        $this->artisan('delivery:sync-store-pause --apply --only-if-open')
            ->expectsOutputToContain('refused this store in the last 24 h')
            ->assertSuccessful();

        Http::assertSentCount(1); // one status call, no pause, nothing on the second run
    }

    public function test_a_store_grab_answers_401_for_is_skipped_for_a_day_too(): void
    {
        // The same DCVIC stores from 2026-10-29 21:00: 401, logged as an ERROR every 10 min.
        $this->bindGrabStore(4609, '4-REFUSED', [11 => ['qty' => 5, 'capacity' => 10]], isActive: false);
        Http::fake([
            '*/store/status' => Http::response(['reason' => 'unauthorized'], 401),
            '*/partner/v1/merchant/pause' => Http::response(null, 401),
        ]);

        $this->artisan('delivery:sync-store-pause --apply --only-if-open')
            ->expectsOutputToContain('Grab answers 401')
            ->assertSuccessful();
        $this->artisan('delivery:sync-store-pause --apply --only-if-open')
            ->expectsOutputToContain('refused this store in the last 24 h')
            ->assertSuccessful();

        Http::assertSentCount(1); // one status call, no pause, nothing on the second run
    }

    public function test_the_pause_reassert_stops_at_grabs_rate_limit(): void
    {
        $this->bindGrabStore(4754, '4-PAUSED-A', [11 => ['qty' => 5, 'capacity' => 10]], isActive: false);
        $this->bindGrabStore(4755, '4-PAUSED-B', [11 => ['qty' => 5, 'capacity' => 10]], isActive: false);
        Http::fake([
            '*/store/status' => Http::response(null, 429),
            '*/partner/v1/merchant/pause' => Http::response(null, 204),
        ]);

        $this->artisan('delivery:sync-store-pause --apply --only-if-open')
            ->expectsOutputToContain('stopped: Grab rate limit')
            ->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_the_pause_reassert_is_scheduled_unless_switched_off(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map->command->filter();

        $this->assertTrue($events->contains(fn ($c) => str_contains($c, 'delivery:sync-store-pause --apply --only-if-open')));
        $this->assertFalse($events->contains(fn ($c) => str_contains($c, 'grab:prune-menu-records')), 'pruning waits for GRAB_MENU_RECORDS_KEEP_DAYS');
    }

    public function test_pruning_deletes_only_old_menu_records_and_only_with_apply(): void
    {
        foreach ([40, 31, 2] as $daysAgo) {
            $record = DeliveryPlatformMenuRecord::create(['ref_id' => "job-{$daysAgo}", 'delivery_platform_slug' => 'grab-menu']);
            $record->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        }

        $this->artisan('grab:prune-menu-records --days=30')->expectsOutputToContain('would delete 2')->assertSuccessful();
        $this->assertSame(3, DeliveryPlatformMenuRecord::count());

        $this->artisan('grab:prune-menu-records --days=30 --apply')->assertSuccessful();
        $this->assertSame(['job-2'], DeliveryPlatformMenuRecord::pluck('ref_id')->all());
    }

    public function test_grab_health_runs_read_only(): void
    {
        $this->bindGrabStore(4416, '4-STORE', [11 => ['qty' => 5, 'capacity' => 10]]);

        $this->artisan('grab:health --days=7')
            ->expectsOutputToContain('Stock held for Grab orders')
            ->assertSuccessful();
    }
}
