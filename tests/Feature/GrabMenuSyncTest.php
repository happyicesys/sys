<?php

namespace Tests\Feature;

use App\Jobs\NotifyDeliveryPlatformUpdateMenu;
use App\Jobs\ProcessGrabMenuWebhook;
use App\Jobs\UpdateDeliveryMenuJson;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use App\Models\GrabMenuProduct;
use App\Models\GrabMenuSync;
use App\Models\GrabMenuSyncStore;
use App\Models\Operator;
use App\Models\User;
use App\Models\VendChannel;
use App\Services\DeliveryPlatformService;
use App\Services\Grab\Menu\GrabMenuService;
use App\Services\Grab\Menu\GrabMenuSyncTracker;
use App\Services\GrabStoreService;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * Grab Menu Sync (Brian, 2026-11-21): "one tab which will record every action and whether it is fully synced …
 * the details on each of the machines", and "the sys … should self check if Grab already has the price rule".
 * On 2026-11-21 two company-rule saves drew 429 "too many requests" for 30 and 16 of 63 stores, which nothing
 * asked again: the prices on Grab ended up mixed.
 */
class GrabMenuSyncTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const A = '4-C7AAAAAAAAAAAA';

    private const B = '4-C7BBBBBBBBBBBB';

    private const SUB = ['id' => 'SGITEDP20200707065112014691', 'name' => 'Ice Cream Sticks'];

    private DeliveryProductMappingVend $a;

    private DeliveryProductMappingVend $b;

    private int $operatorId;

    private int $u49;

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.daily_sync_spacing_seconds' => 3, 'grab.menu_notify_delay_seconds' => 180]);
        Queue::fake();
        $this->buildGrabOperator();
        $this->operatorId = $this->grabMapping->operator_id;
        $this->u49 = $this->grabItem('U-49', 11, 280)->product_id;

        $this->a = $this->bindGrabStore(4754, self::A, [11 => ['qty' => 3, 'capacity' => 10]]);
        $this->b = $this->bindGrabStore(4755, self::B, [11 => ['qty' => 3, 'capacity' => 10]]);
        VendChannel::query()->where('code', 11)->update(['product_id' => $this->u49]);
        DB::table('selling_prices')->insert(['product_id' => $this->u49, 'type' => 2, 'amount' => 320, 'created_at' => now(), 'updated_at' => now()]);
        GrabMenuProduct::create(['operator_id' => $this->operatorId, 'product_id' => $this->u49, 'sub_category_json' => self::SUB]);

        foreach ([self::A, self::B] as $merchantId) {
            app(GrabStoreService::class)->setMenuSource(DeliveryPlatformRefNumber::query()->where('ref_number', $merchantId)->sole(), true, null);
        }
        // Start from a clean log: the two switches above are changes of their own. Their queued asks hold the
        // one-job-per-store locks, which a real worker releases when the job starts.
        GrabMenuSync::query()->delete();
        $this->releaseLocks();
        Queue::fake();
    }

    private function saveRule(int $multiplierBp, ?int $userId = null): GrabMenuSync
    {
        app(GrabMenuService::class)->saveSetting($this->operatorId, ['pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier_bp' => $multiplierBp, 'round_up_cents' => 10], $userId);

        return GrabMenuSync::query()->latest('id')->firstOrFail();
    }

    // Runs the ask as a worker would: the store's one-job lock is released as the job starts.
    private function notify(DeliveryProductMappingVend $binding, bool $force = false, int $retries = 0): void
    {
        $this->releaseLocks();
        (new NotifyDeliveryPlatformUpdateMenu($binding, $force, $retries))->handle(app(DeliveryPlatformService::class));
    }

    private function releaseLocks(): void
    {
        foreach ([$this->a, $this->b] as $binding) {
            foreach ([false, true] as $force) {
                (new UniqueLock(app(Repository::class)))->release(new NotifyDeliveryPlatformUpdateMenu($binding, $force));
            }
        }
    }

    private function row(DeliveryProductMappingVend $binding, ?GrabMenuSync $sync = null): GrabMenuSyncStore
    {
        return GrabMenuSyncStore::query()->where('delivery_product_mapping_vend_id', $binding->id)
            ->when($sync, fn ($query) => $query->where('grab_menu_sync_id', $sync->id))
            ->latest('id')->firstOrFail();
    }

    // Grab pulls the menu (GET /merchant/menu) and its job reports back.
    private function grabPulls(DeliveryProductMappingVend $binding): void
    {
        $menu = app(DeliveryPlatformService::class)->getMenu(self::merchantOf($binding));
        (new UpdateDeliveryMenuJson($binding->id, $menu, now()->toIso8601String()))->handle();
    }

    private function grabSays(string $merchantId, string $status, array $extra = []): void
    {
        (new ProcessGrabMenuWebhook(['jobID' => uniqid(), 'merchantID' => $merchantId, 'status' => $status, 'updatedAt' => now()->utc()->toIso8601String(), ...$extra]))->handle();
    }

    private static function merchantOf(DeliveryProductMappingVend $binding): string
    {
        return $binding->fresh()->deliveryPlatformRefNumber->ref_number;
    }

    public function test_a_company_rule_save_is_one_change_listing_each_store_and_asks_grab_a_few_seconds_apart(): void
    {
        $user = User::factory()->create(['operator_id' => $this->operatorId]);
        $this->saveRule(12500, $user->id);
        $sync = $this->saveRule(10200, $user->id);

        $this->assertSame(GrabMenuSync::KIND_COMPANY_RULE, $sync->kind);
        $this->assertSame('Company price rule of HIPL: RP2 × 1.25, round up 10¢ → RP2 × 1.02, round up 10¢', $sync->summary);
        $this->assertSame($user->id, $sync->user_id);
        $this->assertSame(2, $sync->store_count);
        $this->assertSame([GrabMenuSyncStore::STATUS_WAITING, GrabMenuSyncStore::STATUS_WAITING], $sync->stores()->pluck('status')->all());

        // Spaced: the first save's two asks at 180 s and 183 s (the second save's ride along on them).
        $delays = [];
        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class, function ($job) use (&$delays) {
            $delays[] = (int) round(now()->diffInSeconds($job->delay, true));

            return true;
        });
        $this->assertContains(180, $delays);
        $this->assertContains(183, $delays);
    }

    public function test_a_store_is_synced_only_once_grab_pulled_the_menu_and_confirmed_it(): void
    {
        Http::fake(['*/partner/v1/merchant/menu/notification' => Http::response([], 200)]);
        $sync = $this->saveRule(12500);

        $this->notify($this->a);
        $row = $this->row($this->a, $sync);
        $this->assertSame(GrabMenuSyncStore::STATUS_SENT, $row->status);
        $this->assertSame(1, $row->attempts);
        $this->assertSame(['P'.$this->u49 => 400], $row->expected_json, 'RP2 $3.20 × 1.25 = $4.00, the price sent');

        $this->grabPulls($this->a);
        $this->assertSame(GrabMenuSyncStore::STATUS_PULLED, $this->row($this->a, $sync)->status);

        $this->grabSays(self::A, 'QUEUEING');
        $this->assertSame(GrabMenuSyncStore::STATUS_PULLED, $this->row($this->a, $sync)->status, 'QUEUEING changes nothing');
        $this->grabSays(self::A, 'SUCCESS');
        $row = $this->row($this->a, $sync);
        $this->assertSame(GrabMenuSyncStore::STATUS_SYNCED, $row->status);
        $this->assertNotNull($row->synced_at);
        $this->assertSame(GrabMenuSyncStore::STATUS_WAITING, $this->row($this->b, $sync)->status, 'the other store is untouched');
    }

    public function test_grab_failing_the_menu_reads_not_synced_with_its_reason(): void
    {
        Http::fake(['*/partner/v1/merchant/menu/notification' => Http::response([], 200)]);
        $sync = $this->saveRule(12500);
        $this->notify($this->a);

        $this->grabSays(self::A, 'FAILED', ['errors' => ['item P1 price invalid']]);

        $row = $this->row($this->a, $sync);
        $this->assertSame(GrabMenuSyncStore::STATUS_FAILED, $row->status);
        $this->assertStringContainsString('item P1 price invalid', $row->last_message);
    }

    public function test_too_many_requests_is_asked_again_with_a_growing_wait_then_given_up(): void
    {
        Http::fake(['*/partner/v1/merchant/menu/notification' => Http::response('', 429)]);
        $sync = $this->saveRule(12500);
        Queue::fake();

        $this->notify($this->a);

        $row = $this->row($this->a, $sync);
        $this->assertSame(GrabMenuSyncStore::STATUS_TURNED_AWAY, $row->status);
        $this->assertSame(429, $row->last_code);
        $this->assertNotNull($row->retry_at);
        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class, function ($job) {
            $retries = (fn () => $this->retries)->call($job);
            $wait = now()->diffInSeconds($job->delay, true);

            // 60 s + up to 30 s of spread, measured a moment after the dispatch.
            return $retries === 1 && $wait >= 55 && $wait <= 91;
        });

        // The last allowed ask is turned away too: nothing more is queued and the store reads Not synced.
        Queue::fake();
        $this->notify($this->a, false, NotifyDeliveryPlatformUpdateMenu::MAX_BUSY_RETRIES);
        Queue::assertNotPushed(NotifyDeliveryPlatformUpdateMenu::class);
        $row = $this->row($this->a, $sync);
        $this->assertSame(GrabMenuSyncStore::STATUS_FAILED, $row->status);
        $this->assertSame(2, $row->attempts);
        $this->assertStringContainsString('gave up', $row->last_message);
    }

    public function test_a_menu_grab_already_has_reads_synced_and_a_store_not_selling_reads_not_sent(): void
    {
        Http::fake(['*/partner/v1/merchant/menu/notification' => Http::response([], 200)]);
        $this->grabPulls($this->a);
        $sync = $this->saveRule(10000);   // RP2 × 1 = what Grab pulled already

        $this->notify($this->a);
        $this->assertSame(GrabMenuSyncStore::STATUS_SYNCED, $this->row($this->a, $sync)->status);
        Http::assertNothingSent();

        $this->b->update(['is_active' => false]);
        $this->notify($this->b);
        $this->assertSame(GrabMenuSyncStore::STATUS_SKIPPED, $this->row($this->b, $sync)->status);
    }

    public function test_the_system_check_sends_again_a_store_whose_prices_on_grab_differ_and_records_the_ones_that_match(): void
    {
        Http::fake(['*/partner/v1/merchant/menu/notification' => Http::response([], 200)]);
        $this->grabPulls($this->a);
        $this->grabPulls($this->b);
        // Store A's Grab copy still carries an old price (a turned-away save nobody asked again).
        $stale = $this->a->fresh()->last_menu_json;
        $stale['categories'][0]['subCategories'][0]['items'][0]['price'] = 330;
        $this->a->update(['last_menu_json' => $stale]);
        Queue::fake();

        // First sight of a difference: noted, not acted on (a planogram change may be on its way to Grab).
        $first = app(GrabMenuSyncTracker::class)->check();
        $this->assertSame(['moved' => 0, 'matched' => 1, 'differ' => 1, 'resent' => 0], $first);
        Queue::assertNotPushed(NotifyDeliveryPlatformUpdateMenu::class);
        GrabMenuSync::query()->delete();

        // Still different at the next check: sent again.
        $this->travel(10)->minutes();
        $result = app(GrabMenuSyncTracker::class)->check();

        $this->assertSame(['moved' => 0, 'matched' => 1, 'differ' => 1, 'resent' => 1], $result);
        $sync = GrabMenuSync::query()->where('kind', GrabMenuSync::KIND_SELF_CHECK)->sole();
        $this->assertNull($sync->user_id);
        $a = $this->row($this->a, $sync);
        $this->assertSame(GrabMenuSyncStore::STATUS_WAITING, $a->status);
        $this->assertStringContainsString('3.30 → 3.20', $a->last_message);
        $this->assertSame(GrabMenuSyncStore::STATUS_SYNCED, $this->row($this->b, $sync)->status, 'a store with no row yet is recorded as matching');
        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class, fn ($job) => (fn () => $this->force)->call($job) === true);

        // Next check: A is still on its way (no second row); B matches again and is marked checked, with no new change row.
        $this->travel(11)->minutes();
        Queue::fake();
        app(GrabMenuSyncTracker::class)->check();
        $this->assertSame(1, GrabMenuSync::query()->count());
        $this->assertNotNull($this->row($this->b)->last_checked_at);
        Queue::assertNotPushed(NotifyDeliveryPlatformUpdateMenu::class);
    }

    public function test_stuck_rows_are_moved_on_by_the_system_check(): void
    {
        Http::fake(['*/partner/v1/merchant/menu/notification' => Http::response([], 200)]);
        $sync = $this->saveRule(12500);
        $this->notify($this->a);
        $this->notify($this->b);
        $this->grabPulls($this->b);

        $this->travel(21)->minutes();
        Queue::fake();
        Artisan::call('grab:check-menu-sync');

        // A: asked, never pulled → asked again. B: pulled, confirmation never came → taken as on Grab.
        $this->assertSame(GrabMenuSyncStore::STATUS_WAITING, $this->row($this->a, $sync)->status);
        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class, fn ($job) => (fn () => $this->deliveryProductMappingVend->id)->call($job) === $this->a->id);
        $b = $this->row($this->b, $sync);
        $this->assertSame(GrabMenuSyncStore::STATUS_SYNCED, $b->status);
        $this->assertStringContainsString('confirmation never came', $b->last_message);
    }

    public function test_a_store_grab_keeps_failing_is_sent_again_by_the_system_check_at_most_once_a_day(): void
    {
        Http::fake(['*/partner/v1/merchant/menu/notification' => Http::response([], 200)]);
        $this->saveRule(12500);
        $this->notify($this->a);
        $this->grabSays(self::A, 'FAILED', ['errors' => ["subCategories shouldn't be empty"]]);
        $pushesFor = fn () => collect(Queue::pushed(NotifyDeliveryPlatformUpdateMenu::class))
            ->filter(fn ($job) => (fn () => $this->deliveryProductMappingVend->id)->call($job) === $this->a->id)->count();

        // The first check sends it again, and Grab fails it again.
        Queue::fake();
        app(GrabMenuSyncTracker::class)->check();
        $this->assertSame(1, $pushesFor());
        // Grab fails it again (the same menu, the same refusal).
        $this->row($this->a)->update(['status' => GrabMenuSyncStore::STATUS_FAILED, 'last_message' => "Grab could not take the menu: subCategories shouldn't be empty"]);
        $this->assertSame(GrabMenuSyncStore::STATUS_FAILED, $this->row($this->a)->status);

        // Hourly checks the same day leave it alone.
        foreach ([2, 6, 12] as $hours) {
            $this->travelTo(now()->addHours($hours));
            Queue::fake();
            app(GrabMenuSyncTracker::class)->check();
            $this->assertSame(0, $pushesFor(), "sent again after {$hours} h");
            $this->travelBack();
        }

        // A day later it is tried once more.
        $this->travel(25)->hours();
        Queue::fake();
        app(GrabMenuSyncTracker::class)->check();
        $this->assertSame(1, $pushesFor());
    }

    public function test_a_queued_ask_that_never_runs_is_given_up_instead_of_queued_forever(): void
    {
        $sync = $this->saveRule(12500); // the jobs are faked, so neither ask ever runs

        $this->travel(31)->minutes();
        Queue::fake();
        app(GrabMenuSyncTracker::class)->check();
        $this->assertSame(GrabMenuSyncStore::STATUS_WAITING, $this->row($this->a, $sync)->status, 'queued again first');
        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class);

        $this->travel(3)->hours();
        app(GrabMenuSyncTracker::class)->check();
        $row = $this->row($this->a, $sync);
        $this->assertSame(GrabMenuSyncStore::STATUS_FAILED, $row->status);
        $this->assertStringContainsString('never ran', $row->last_message);
    }

    public function test_the_page_lists_changes_opens_their_stores_and_asks_again(): void
    {
        Http::fake(['*/partner/v1/merchant/menu/notification' => Http::response('', 429)]);
        $sync = $this->saveRule(12500);
        $this->notify($this->a, false, NotifyDeliveryPlatformUpdateMenu::MAX_BUSY_RETRIES);
        $user = $this->staff();

        $this->actingAs($user)->get('/grab-menu-sync')->assertOk()
            ->assertInertia(fn ($page) => $page->component('GrabMenu/Sync')
                ->where('syncs.data.0.id', $sync->id)
                ->where('syncs.data.0.state', 'failed')
                ->where('syncs.data.0.counts.failed', 1)
                ->where('syncs.data.0.counts.moving', 1));

        $this->actingAs($user)->get('/grab-menu-sync?state=attention')->assertInertia(fn ($page) => $page->has('syncs.data', 1));
        $this->actingAs($user)->get('/grab-menu-sync?state=synced')->assertInertia(fn ($page) => $page->has('syncs.data', 0));

        $this->actingAs($user)->getJson("/grab-menu-sync/{$sync->id}/stores")->assertOk()
            ->assertJsonPath('stores.0.vend_code', '4754')
            ->assertJsonPath('stores.0.status', GrabMenuSyncStore::STATUS_FAILED)
            ->assertJsonPath('stores.0.last_code', 429);

        Queue::fake();
        $this->actingAs($user)->postJson("/grab-menu-sync/{$sync->id}/resend")->assertOk()->assertJsonPath('sent', 1);
        $this->assertSame(GrabMenuSyncStore::STATUS_WAITING, $this->row($this->a, $sync)->status);
        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class, fn ($job) => (fn () => $this->force)->call($job) === true);

        // Grab Menu & Prices' store list carries each store's latest state ("On Grab").
        $this->actingAs($user)->get('/grab-menu?operator_id='.$this->operatorId)->assertOk()
            ->assertInertia(fn ($page) => $page->where('stores.0.on_grab.status', GrabMenuSyncStore::STATUS_WAITING));
    }

    public function test_another_operator_sees_neither_the_change_nor_its_stores(): void
    {
        $sync = $this->saveRule(12500);
        $other = Operator::forceCreate(['name' => 'Kumoyo', 'code' => 'KMY', 'country_id' => Operator::query()->value('country_id'), 'created_by' => 1]);
        $user = $this->staff($other->id);

        $this->actingAs($user)->get('/grab-menu-sync')->assertInertia(fn ($page) => $page->has('syncs.data', 0));
        $this->actingAs($user)->getJson("/grab-menu-sync/{$sync->id}/stores")->assertForbidden();
        $this->actingAs($user)->postJson("/grab-menu-sync/{$sync->id}/resend")->assertForbidden();
    }

    public function test_the_store_list_shows_when_grab_last_took_the_menu_and_when_its_items_and_prices_changed(): void
    {
        $user = $this->staff();
        $times = fn () => DB::table('delivery_product_mapping_vend')->where('id', $this->a->id)->first(['grab_menu_pulled_at', 'grab_items_changed_at', 'grab_prices_changed_at']);

        $this->travelTo(now()->setTime(9, 0));
        $this->grabPulls($this->a);
        $first = $times();
        $this->assertNotNull($first->grab_items_changed_at, 'the first menu Grab took is a change');
        $this->assertNotNull($first->grab_prices_changed_at);

        // The same menu again (Grab's hash unchanged): only the pull moves.
        $this->travel(10)->minutes();
        $this->grabPulls($this->a);
        $same = $times();
        $this->assertNotSame($first->grab_menu_pulled_at, $same->grab_menu_pulled_at);
        $this->assertSame($first->grab_items_changed_at, $same->grab_items_changed_at);
        $this->assertSame($first->grab_prices_changed_at, $same->grab_prices_changed_at);

        // A new rule moves prices, not the items listed; stock and the selling-time date never count.
        $this->travel(10)->minutes();
        $this->saveRule(12500);
        $this->grabPulls($this->a);
        $priced = $times();
        $this->assertNotSame($first->grab_prices_changed_at, $priced->grab_prices_changed_at);
        $this->assertSame($first->grab_items_changed_at, $priced->grab_items_changed_at);

        DB::table('delivery_platform_orders')->insert([
            ['delivery_platform_ref_number_id' => $this->a->delivery_platform_ref_number_id, 'delivery_product_mapping_vend_id' => $this->a->id, 'order_created_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['delivery_platform_ref_number_id' => $this->a->delivery_platform_ref_number_id, 'delivery_product_mapping_vend_id' => $this->a->id, 'order_created_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now()],
            ['delivery_platform_ref_number_id' => $this->a->delivery_platform_ref_number_id, 'delivery_product_mapping_vend_id' => $this->a->id, 'order_created_at' => now()->subDays(10), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($user)->get('/grab-menu?operator_id='.$this->operatorId)->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stores.0.merchant_id', self::A)
                ->where('stores.0.grab_times.prices_changed_at', fn ($at) => $at !== null)
                ->where('stores.0.grab_times.pulled_at', fn ($at) => $at !== null)
                ->where('stores.0.grab_times.items_changed_at', fn ($at) => $at !== null)
                ->where('stores.0.orders', ['d30' => 3, 'd7' => 2, 'yesterday' => 1, 'today' => 1])
                ->where('stores.1.grab_times.pulled_at', null));
    }

    private function staff(?int $operatorId = null): User
    {
        foreach (['read', 'update'] as $verb) {
            Permission::findOrCreate("{$verb} delivery-platform-product-mappings", 'web');
        }
        $user = User::factory()->create(['operator_id' => $operatorId ?? $this->operatorId]);
        $user->givePermissionTo(['read delivery-platform-product-mappings', 'update delivery-platform-product-mappings']);

        return $user;
    }
}
