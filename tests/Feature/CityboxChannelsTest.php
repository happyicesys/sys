<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxProduct;
use App\Models\Product;
use App\Models\ProductMappingItem;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\Citybox\ChannelFrameAdapter;
use App\Services\Citybox\ChillerChannelMap;
use App\Services\Citybox\CityboxOpenapiSync;
use App\Services\Citybox\DTO\ChillerSlot;
use App\Services\Citybox\DTO\ChillerStockLine;
use App\Services\Citybox\StockPollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Citybox\ChillerMapping;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

/**
 * A chiller's channels since 2026-09-21: OUR mapping decides the codes and the
 * products, the SKU decides capacity (products.chiller_slot_qty), and CityBox
 * supplies only quantity and price — per product, which is why a SKU on two
 * codes is split on the way in and summed on the way out.
 *
 * Before this, the channels mirrored CityBox's Pre-Stock Setup, so a template
 * switch in their portal emptied a machine here (prod C5001, 2026-09-19).
 */
class CityboxChannelsTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // SyncVendChannels runs inline (queue=sync) so real vend_channels rows appear;
        // its child jobs take a Redis unique-lock even on the sync driver, so they are faked.
        Queue::fake([\App\Jobs\Vend\SyncVendChannelErrorLog::class, \App\Jobs\Vend\SaveVendChannelsJson::class]);
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
        $this->vend = Vend::create(['code' => 9500, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E1', 'is_active' => 1, 'operator_id' => 1]);
        $this->gw->seedDevice('E1');
    }

    /** Their Pre-Stock Setup: 3 SKUs on layer 1, par 5 each. */
    private function seedPar(): void
    {
        $this->gw->seedPar('E1', [
            ['id' => 90340, 'name' => 'Peach', 'qty' => 5, 'layer' => 1, 'price' => '0.10'],
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 5, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 5, 'layer' => 1, 'price' => '0.11'],
        ]);
    }

    /** Our layout: one code per SKU, capacities 4 / 6 / 2. */
    private function bindMapping(): void
    {
        ChillerMapping::bind($this->vend, [
            101 => [90338, 4],
            102 => [90339, 6],
            203 => [90340, 2],
        ]);
    }

    private function channels(): \Illuminate\Support\Collection
    {
        return VendChannel::where('vend_id', $this->vend->id)->where('is_active', true)->orderBy('code')->get();
    }

    // ── the map itself (pure) ──────────────────────────────────────────────

    public function test_slots_come_from_the_mapping_with_capacity_from_the_sku(): void
    {
        $this->bindMapping();

        $slots = app(ChillerChannelMap::class)->forVend($this->vend->fresh());

        $this->assertSame([101, 102, 203], array_keys($slots));
        $this->assertSame(90338, $slots[101]->cityboxProductId);
        $this->assertSame(4, $slots[101]->capacity);
        $this->assertSame(2, $slots[203]->capacity);
        $this->assertSame(2, $slots[203]->layer());
    }

    public function test_a_product_with_no_citybox_link_gets_no_slot(): void
    {
        $this->bindMapping();
        $ours = Product::create(['code' => 'VM-1', 'name' => 'Our own', 'is_active' => true, 'is_inventory' => true]);
        ProductMappingItem::create(['product_mapping_id' => $this->vend->fresh()->product_mapping_id, 'channel_code' => '104', 'product_id' => $ours->id]);

        $this->assertArrayNotHasKey(104, app(ChillerChannelMap::class)->forVend($this->vend->fresh()));
    }

    public function test_one_sku_on_two_codes_splits_its_quantity_by_capacity(): void
    {
        $slots = [
            101 => new ChillerSlot(101, 90338, 1, 5),
            103 => new ChillerSlot(103, 90338, 1, 5),
            201 => new ChillerSlot(201, 90339, 2, 0), // capacity not measured
        ];

        $this->assertSame([101 => 5, 103 => 2, 201 => 9], ChillerChannelMap::allocate($slots, [90338 => 7, 90339 => 9]));
        $this->assertSame([101 => 5, 103 => 5, 201 => 0], ChillerChannelMap::allocate($slots, [90338 => 10]));
        $this->assertSame([101 => 0, 103 => 0, 201 => 0], ChillerChannelMap::allocate($slots, []));
        // …and back out again: their API takes one count per product.
        $this->assertSame([90338 => 9, 90339 => 4], ChillerChannelMap::sumBySku($slots, [101 => 5, 103 => 4, 201 => 4]));
    }

    public function test_adapter_builds_one_channel_per_slot_with_live_qty_and_price(): void
    {
        $slots = [101 => new ChillerSlot(101, 90338, 1, 4), 102 => new ChillerSlot(102, 90339, 2, 6)];
        $stock = collect([ChillerStockLine::fromApi(['product_id' => 90338, 'name' => 'S', 'quantity' => 3, 'price' => '1.20', 'layer' => 1])]);

        $frame = app(ChannelFrameAdapter::class)->toFrame($stock, $slots, 'A')->toArray();

        $this->assertSame('A', $frame['label']);
        $this->assertSame([101, 102], array_column($frame['channels'], 'channel_code'));
        $this->assertSame([3, 0], array_column($frame['channels'], 'qty'), 'a SKU the live call omits sits at 0, it does not vanish');
        $this->assertSame([4, 6], array_column($frame['channels'], 'capacity'), 'capacity is ours, never their par');
        $this->assertSame(120, $frame['channels'][0]['amount']);
    }

    // ── the poll ───────────────────────────────────────────────────────────

    public function test_poll_writes_channels_from_our_mapping(): void
    {
        $this->seedPar();
        $this->bindMapping();
        $this->gw->seedStock('E1', [
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 3, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90340, 'name' => 'Peach', 'qty' => 1, 'layer' => 1, 'price' => '0.10'],
        ]);

        app(CityboxOpenapiSync::class)->syncAll();

        $channels = $this->channels();
        $this->assertSame([101, 102, 203], $channels->pluck('code')->map(fn ($c) => (int) $c)->all());
        $this->assertSame([3, 0, 1], $channels->pluck('qty')->map(fn ($q) => (int) $q)->all());
        $this->assertSame([4, 6, 2], $channels->pluck('capacity')->map(fn ($c) => (int) $c)->all());
        $this->assertNotNull($channels->firstWhere('code', 101)->product_id);
    }

    public function test_their_template_switch_no_longer_empties_our_planogram(): void
    {
        // Prod 2026-09-19, C5001: their template dropped to one SKU and the mirror
        // followed, leaving 60 channels with no product. Ours does not move.
        $this->seedPar();
        $this->bindMapping();
        $this->gw->seedStock('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 2, 'layer' => 1, 'price' => '0.12']]);
        app(CityboxOpenapiSync::class)->syncAll();
        $this->assertCount(3, $this->channels());

        $this->gw->seedPar('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 5, 'layer' => 1, 'price' => '0.12']]);
        app(CityboxOpenapiSync::class)->pull($this->vend->fresh());

        $this->assertSame([101, 102, 203], $this->channels()->pluck('code')->map(fn ($c) => (int) $c)->all());
        $this->assertSame(0, VendChannel::where('vend_id', $this->vend->id)->where('is_active', true)->whereNull('product_id')->count());
        $this->assertSame(3, ProductMappingItem::where('product_mapping_id', $this->vend->fresh()->product_mapping_id)->count());
    }

    public function test_their_par_change_does_not_touch_our_capacity(): void
    {
        $this->seedPar();
        $this->bindMapping();
        $this->gw->seedStock('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 1, 'layer' => 1, 'price' => '0.12']]);
        app(CityboxOpenapiSync::class)->syncAll();

        $this->gw->seedPar('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 99, 'layer' => 1, 'price' => '0.12']]);
        app(CityboxOpenapiSync::class)->pull($this->vend->fresh());

        $this->assertSame(4, (int) $this->channels()->firstWhere('code', 101)->capacity);
    }

    public function test_a_channel_dropped_from_our_mapping_is_retired(): void
    {
        $this->seedPar();
        $this->bindMapping();
        $this->gw->seedStock('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 1, 'layer' => 1, 'price' => '0.12']]);
        app(CityboxOpenapiSync::class)->syncAll();

        ProductMappingItem::where('product_mapping_id', $this->vend->fresh()->product_mapping_id)
            ->where('channel_code', '102')->delete();
        app(CityboxOpenapiSync::class)->pull($this->vend->fresh());

        $this->assertSame([101, 203], $this->channels()->pluck('code')->map(fn ($c) => (int) $c)->all());
        $this->assertFalse((bool) VendChannel::where('vend_id', $this->vend->id)->where('code', 102)->first()->is_active);
    }

    public function test_a_chiller_with_no_mapping_gets_no_channels_but_still_polls(): void
    {
        $this->seedPar();
        $this->gw->seedStock('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 4, 'layer' => 1, 'price' => '0.12']]);

        app(CityboxOpenapiSync::class)->syncAll();

        $this->assertCount(0, $this->channels());
        $this->assertDatabaseCount('citybox_inventory_polls', 1);
    }

    public function test_a_sold_out_channel_keeps_its_price(): void
    {
        // Their live call omits a product the machine holds none of. Prod 2026-09-21: every
        // channel on C6003 read S$0.00, zeroing stock value and refill amounts.
        $this->seedPar();
        $this->bindMapping();
        $this->gw->seedStock('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 2, 'layer' => 1, 'price' => '0.12']]);

        app(CityboxOpenapiSync::class)->syncAll();

        $channels = $this->channels();
        $this->assertSame(12, (int) $channels->firstWhere('code', 101)->amount, 'in stock: the live price');
        $this->assertSame(11, (int) $channels->firstWhere('code', 102)->amount, 'sold out: their config price');
        $this->assertSame(10, (int) $channels->firstWhere('code', 203)->amount);
    }

    public function test_emptying_the_mapping_retires_every_channel(): void
    {
        $this->seedPar();
        $this->bindMapping();
        $this->gw->seedStock('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 2, 'layer' => 1, 'price' => '0.12']]);
        app(CityboxOpenapiSync::class)->syncAll();
        $this->assertCount(3, $this->channels());

        $this->vend->forceFill(['product_mapping_id' => null])->save();
        app(CityboxOpenapiSync::class)->pull($this->vend->fresh());

        $this->assertCount(0, $this->channels(), 'no planogram means no channels, not the old layout');
    }

    // ── recognition check against THEIR config ─────────────────────────────

    public function test_a_sku_their_machine_does_not_carry_is_reported(): void
    {
        // Their AI only recognises what that machine's Pre-Stock Setup carries.
        $this->gw->seedPar('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 5, 'layer' => 1, 'price' => '0.12']]);
        $this->bindMapping(); // 90339 and 90340 are NOT in their config
        $this->gw->seedStock('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 1, 'layer' => 1, 'price' => '0.12']]);
        app(CityboxOpenapiSync::class)->syncAll();

        $missing = app(StockPollService::class)->unrecognisableSlots($this->vend->fresh(), fresh: true);

        $this->assertSame([102, 203], array_column($missing, 'code'));
        $this->assertSame([90339, 90340], array_column($missing, 'citybox_product_id'));
    }

    public function test_nothing_is_reported_when_their_config_is_unknown(): void
    {
        $this->bindMapping();

        $this->assertSame([], app(StockPollService::class)->unrecognisableSlots($this->vend->fresh()));
    }

    // ── the overview endpoint ──────────────────────────────────────────────

    public function test_planogram_endpoint_returns_five_layers_with_channels_and_totals(): void
    {
        $this->seedPar();
        $this->bindMapping();
        $this->gw->seedStock('E1', [
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 1, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 0, 'layer' => 1, 'price' => '0.11'],
        ]);
        app(CityboxOpenapiSync::class)->syncAll();

        $r = $this->actingAs(User::factory()->create())
            ->getJson("/vends/{$this->vend->id}/citybox-planogram")->assertOk()->json();

        $this->assertCount(5, $r['layers']);
        $this->assertSame(1, $r['layers'][0]['layer']);
        $this->assertCount(2, $r['layers'][0]['channels']); // 101, 102
        $this->assertCount(1, $r['layers'][1]['channels']); // 203
        $this->assertSame([1, 12], [$r['total_qty'], $r['total_capacity']]);
        $this->assertSame(0, $r['unmapped_count']);
        $this->assertTrue($r['refreshed']);
    }

    public function test_planogram_keeps_a_deactivated_product_and_flags_it_for_greying(): void
    {
        $this->seedPar();
        $this->bindMapping();
        $this->gw->seedStock('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 4, 'layer' => 1, 'price' => '0.12']]);
        app(CityboxOpenapiSync::class)->syncAll();
        Product::where('code', '90338')->first()->forceFill(['is_active' => false])->save();

        $r = $this->actingAs(User::factory()->create())
            ->getJson("/vends/{$this->vend->id}/citybox-planogram")->assertOk()->json();

        $suntory = collect($r['layers'][0]['channels'])->firstWhere('code', 101);
        $this->assertNotNull($suntory, 'the channel must survive its product being deactivated');
        $this->assertFalse($suntory['product']['is_active']);
        $this->assertSame(4, $suntory['qty']);
    }

    public function test_stock_we_do_not_map_is_listed_as_off_planogram(): void
    {
        $noodle = Product::create(['code' => '90332', 'name' => 'KSF Cup Noodle', 'is_active' => false]);
        CityboxProduct::create(['citybox_product_id' => 90332, 'name' => 'Cup Noodle', 'product_id' => $noodle->id, 'img_url' => 'https://cdn/n.png', 'first_seen_at' => now()]);
        $this->seedPar();
        $this->bindMapping();
        $this->gw->seedStock('E1', [
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 1, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90332, 'name' => 'Cup Noodle', 'qty' => 5, 'layer' => 1, 'price' => '0.23'],
            // Channel-less but empty: a leftover, not hidden stock.
            ['id' => 90328, 'name' => 'Snow Crackers', 'qty' => 0, 'layer' => 1, 'price' => '1.80'],
        ]);
        app(CityboxOpenapiSync::class)->syncAll();

        $r = $this->actingAs(User::factory()->create())
            ->getJson("/vends/{$this->vend->id}/citybox-planogram")->assertOk()->json();

        $this->assertCount(1, $r['off_planogram']);
        $this->assertSame(5, $r['off_planogram_qty']);
        $off = $r['off_planogram'][0];
        $this->assertSame(90332, $off['citybox_product_id']);
        $this->assertSame('KSF Cup Noodle', $off['product']['name']);
        $this->assertSame(1, $r['total_qty'], 'off-planogram stock stays out of the cabinet totals');
    }

    public function test_planogram_reports_the_unrecognisable_slots(): void
    {
        $this->gw->seedPar('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 5, 'layer' => 1, 'price' => '0.12']]);
        $this->bindMapping();
        $this->gw->seedStock('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 1, 'layer' => 1, 'price' => '0.12']]);
        app(CityboxOpenapiSync::class)->syncAll();

        $r = $this->actingAs(User::factory()->create())
            ->getJson("/vends/{$this->vend->id}/citybox-planogram")->assertOk()->json();

        $this->assertSame([102, 203], array_column($r['unrecognisable'], 'code'));
    }

    public function test_overview_pulls_live_before_answering_and_survives_a_failed_pull(): void
    {
        $this->seedPar();
        $this->bindMapping();
        $this->gw->seedStock('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 2, 'layer' => 1, 'price' => '0.12']]);
        app(CityboxOpenapiSync::class)->syncAll();
        $user = User::factory()->create();

        // Their side moves after our last poll.
        $this->gw->seedStock('E1', [['id' => 90338, 'name' => 'Suntory', 'qty' => 9, 'layer' => 1, 'price' => '2.50']]);
        $r = $this->actingAs($user)->getJson("/vends/{$this->vend->id}/citybox-planogram")->assertOk()->json();
        $suntory = collect($r['layers'][0]['channels'])->firstWhere('code', 101);
        $this->assertSame(9, $suntory['qty'], 'the overview shows the LIVE qty, not the last poll');
        $this->assertSame(250, $suntory['amount_cents']);
        $this->assertTrue($r['refreshed']);

        // Their fleet call stops knowing the device: the last sync still renders.
        unset($this->gw->devices['E1']);
        $r = $this->actingAs($user)->getJson("/vends/{$this->vend->id}/citybox-planogram")->assertOk()->json();
        $this->assertFalse($r['refreshed']);
        $this->assertSame(9, collect($r['layers'][0]['channels'])->firstWhere('code', 101)['qty']);
    }

    public function test_planogram_endpoint_is_403_for_a_non_chiller(): void
    {
        $vending = Vend::create(['code' => 9501, 'is_active' => 1, 'operator_id' => 1]);

        $this->actingAs(User::factory()->create())
            ->getJson("/vends/{$vending->id}/citybox-planogram")->assertForbidden();
    }
}
