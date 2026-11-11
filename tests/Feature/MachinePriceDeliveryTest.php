<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Jobs\Vend\PushMappingMenuSync;
use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\Product\MachinePriceDelivery;
use App\Services\VendJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The machine page shows the live Ref Price and whether the machine has taken it (Brian, 2026-11-11; machine 10033 in
 * Indonesia showed "Ref Price(3)" over RP2 figures from a stale channel snapshot, and nobody could tell whether the
 * machine had the new price).
 *
 * Pinned here: the machine's own `/thumbnails` fetch records the prices it was served (a browser's does not); the
 * report compares them with what it would be served now (current / stale / unknown, nothing for a board-price
 * machine); a Site RP change and a selling price change record the push and rebuild the page's snapshot; a price-only
 * push sends a 315+ big board the light re-read, not the full image download.
 */
class MachinePriceDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const OKHTTP = 'okhttp/4.12.0';

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create(['code' => 'DJ-CP19', 'name' => 'Watermelon Berry']);
        // The mutator stores cents: 320.00 → 32000 (a rupiah figure ×100 would be the same shape).
        SellingPrice::create(['product_id' => $this->product->id, 'type' => SellingPrice::TYPE_2, 'amount' => 320.00]);
        SellingPrice::create(['product_id' => $this->product->id, 'type' => SellingPrice::TYPE_3, 'amount' => 280.00]);
    }

    private function makeVend(int $code, int $siteRp = SellingPrice::TYPE_2, bool $followsSite = true, int $apk = 323): Vend
    {
        $customer = Customer::create([
            'name' => "Site {$code}", 'code' => $code, 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => $siteRp,
        ]);
        $mapping = ProductMapping::forceCreate(['name' => "Mapping {$code}"]);
        ProductMappingItem::create([
            'product_mapping_id' => $mapping->id, 'channel_code' => '10', 'product_id' => $this->product->id,
        ]);
        $vend = Vend::forceCreate([
            'code' => $code, 'customer_id' => $customer->id, 'product_mapping_id' => $mapping->id,
            'is_using_server_price' => $followsSite, 'operator_id' => 1, 'is_active' => true,
            'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'apk_ver_json' => ['apkver' => $apk, 'buildtime' => '2026-11-01', 'deviceType' => 'ZC-328'],
            'mqtt_last_updated_at' => now(),
        ]);
        VendChannel::forceCreate([
            'vend_id' => $vend->id, 'code' => 10, 'product_id' => $this->product->id,
            'amount' => 32000, 'qty' => 5, 'capacity' => 8, 'is_active' => 1,
        ]);

        return $vend;
    }

    private function report(Vend $vend): array
    {
        return app(MachinePriceDelivery::class)->report(Vend::withoutGlobalScopes()->find($vend->id));
    }

    private function editor(array $permissions = ['update machine-settings', 'update customers', 'update products']): User
    {
        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    /** @return list<string> the Type of every MQTT frame queued */
    private function frameTypes(): array
    {
        $types = [];
        Queue::assertPushed(PublishMqtt::class, function (PublishMqtt $job) use (&$types) {
            [, $wire] = (fn () => [$this->topic, $this->message])->call($job);
            [, , $b64] = explode(',', $wire);
            $types[] = json_decode(base64_decode($b64), true)['Type'] ?? null;

            return true;
        });

        return $types;
    }

    public function test_the_machines_own_fetch_records_the_prices_it_was_served_and_a_browser_does_not(): void
    {
        $vend = $this->makeVend(10033);

        $this->get('/api/vends/10033/thumbnails', ['User-Agent' => 'Mozilla/5.0'])->assertOk();
        $this->assertNull(DB::table('vends')->where('id', $vend->id)->value('price_menu_fetched_at'));
        $this->assertSame('unknown', $this->report($vend)['state']);

        $this->get('/api/vends/10033/thumbnails', ['User-Agent' => self::OKHTTP])
            ->assertOk()
            ->assertJsonPath('0.server_price', 32000);

        $row = DB::table('vends')->where('id', $vend->id)->first(['price_menu_fetched_at', 'price_menu_prices_json']);
        $this->assertNotNull($row->price_menu_fetched_at);
        $this->assertSame(['10' => 32000], json_decode($row->price_menu_prices_json, true));

        $report = $this->report($vend);
        $this->assertSame('current', $report['state']);
        $this->assertSame('current', $report['channels']['10']['state']);
        $this->assertSame(32000, $report['channels']['10']['ref_price']);
    }

    public function test_a_price_change_reads_waiting_until_the_machine_reloads(): void
    {
        $vend = $this->makeVend(10033);
        $this->get('/api/vends/10033/thumbnails', ['User-Agent' => self::OKHTTP])->assertOk();

        // RP2 drops to 280.00 (the page deletes the row and adds a new one).
        SellingPrice::where('product_id', $this->product->id)->where('type', SellingPrice::TYPE_2)->delete();
        SellingPrice::create(['product_id' => $this->product->id, 'type' => SellingPrice::TYPE_2, 'amount' => 280.00]);

        $report = $this->report($vend);
        $this->assertSame('stale', $report['state']);
        $this->assertSame(1, $report['stale_count']);
        $this->assertEquals(['loaded' => 32000, 'expected' => 28000, 'ref_price' => 28000],
            array_intersect_key($report['channels']['10'], array_flip(['loaded', 'expected', 'ref_price'])));

        // The machine re-reads its menu: up to date.
        $this->get('/api/vends/10033/thumbnails', ['User-Agent' => self::OKHTTP])->assertJsonPath('0.server_price', 28000);
        $this->assertSame('current', $this->report($vend)['state']);
    }

    public function test_a_site_rp_change_is_waiting_too_and_follows_the_live_tier(): void
    {
        $vend = $this->makeVend(10033);
        $this->get('/api/vends/10033/thumbnails', ['User-Agent' => self::OKHTTP])->assertOk();

        Customer::whereKey($vend->customer_id)->update(['selling_price_type' => SellingPrice::TYPE_3]);

        $report = $this->report($vend);
        $this->assertSame(3, $report['tier']);
        $this->assertSame('stale', $report['channels']['10']['state']);
        $this->assertSame(28000, $report['channels']['10']['ref_price']);
    }

    public function test_a_board_price_machine_has_nothing_to_deliver_but_shows_the_live_ref_price(): void
    {
        $vend = $this->makeVend(10034, followsSite: false);
        $this->get('/api/vends/10034/thumbnails', ['User-Agent' => self::OKHTTP])->assertJsonPath('0.server_price', null);

        $report = $this->report($vend);
        $this->assertFalse($report['follows_site']);
        $this->assertNull($report['state']);
        $this->assertNull($report['channels']['10']['state']);
        $this->assertSame(32000, $report['channels']['10']['ref_price']);
    }

    public function test_the_site_rp_nudge_records_the_push_and_rebuilds_the_page_snapshot(): void
    {
        // A Site RP change calls nudge() for every machine that follows the Site (CustomerController::update).
        Queue::fake();
        $follower = $this->makeVend(10033);

        app(\App\Services\VendPricingSourceService::class)->nudge($follower);

        $this->assertNotNull(DB::table('vends')->where('id', $follower->id)->value('price_menu_pushed_at'));
        Queue::assertPushed(SaveVendChannelsJson::class);
        $this->assertContains('TYPESYNCAPICHANNELSLOTLIST', $this->frameTypes());
    }

    public function test_a_price_only_push_sends_a_315_board_the_light_re_read_and_records_it(): void
    {
        Queue::fake();
        $new = $this->makeVend(10033, apk: 323);
        $old = $this->makeVend(10017, apk: 194);
        // Both on one mapping, as PushMappingMenuSync works per mapping.
        Vend::withoutGlobalScopes()->whereKey($old->id)->update(['product_mapping_id' => $new->product_mapping_id]);

        (new PushMappingMenuSync($new->product_mapping_id, serverPriceOnly: true))->handle(app(VendJobService::class));

        $types = $this->frameTypes();
        $this->assertSame(['HAPPYHOUR', 'TYPESYNCAPICHANNELSLOTLIST'], collect($types)->sort()->values()->all());
        $this->assertNotNull(DB::table('vends')->where('id', $new->id)->value('price_menu_pushed_at'));
        $this->assertNotNull(DB::table('vends')->where('id', $old->id)->value('price_menu_pushed_at'));
        Queue::assertPushed(SaveVendChannelsJson::class, 2);
    }

    public function test_the_page_endpoint_and_send_again(): void
    {
        Queue::fake();
        $vend = $this->makeVend(10033);

        $this->actingAs($this->editor([]))
            ->getJson('/vends/'.$vend->id.'/price-delivery')
            ->assertOk()
            ->assertJsonPath('tier', 2)
            ->assertJsonPath('state', 'unknown')
            ->assertJsonPath('channels.10.ref_price', 32000);

        // Send again needs the machine settings permission.
        $this->actingAs($this->editor([]))->postJson('/vends/'.$vend->id.'/price-delivery/push')->assertForbidden();
        $this->actingAs($this->editor(['update machine-settings']))
            ->postJson('/vends/'.$vend->id.'/price-delivery/push')
            ->assertOk()
            ->assertJsonPath('vend_id', $vend->id);
        $this->assertNotNull(DB::table('vends')->where('id', $vend->id)->value('price_menu_pushed_at'));
    }

    public function test_only_the_machine_app_counts_as_a_fetch(): void
    {
        $this->assertTrue(MachinePriceDelivery::isMachineClient('okhttp/4.12.0'));
        $this->assertTrue(MachinePriceDelivery::isMachineClient('okhttp/3.12.1'));
        $this->assertTrue(MachinePriceDelivery::isMachineClient('Dalvik/2.1.0 (Linux; U; Android 7.1.2)'));
        $this->assertFalse(MachinePriceDelivery::isMachineClient('Mozilla/5.0 (Macintosh)'));
        $this->assertFalse(MachinePriceDelivery::isMachineClient('curl/8.4.0'));
        $this->assertFalse(MachinePriceDelivery::isMachineClient(null));
    }
}
