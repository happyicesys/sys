<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxProduct;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\Citybox\ChannelFrameAdapter;
use App\Services\Citybox\ChillerPlanogram;
use App\Services\Citybox\CityboxOpenapiSync;
use App\Services\Citybox\DTO\ChillerStockLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

class CityboxChannelsTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // SyncVendChannels itself runs inline (queue=sync) so real vend_channels
        // rows appear; its CHILD jobs (error-log / json snapshot) take a Redis
        // unique-lock even on the sync driver, so those two are faked. Same
        // constraint the vending fleet's own job has always had in tests.
        Queue::fake([\App\Jobs\Vend\SyncVendChannelErrorLog::class, \App\Jobs\Vend\SaveVendChannelsJson::class]);
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
        $this->vend = Vend::create(['code' => 9500, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E1', 'is_active' => 1, 'operator_id' => 1]);
        $this->gw->seedDevice('E1');
    }

    /** #1's real planogram: 3 SKUs on layer 1, par 5 each. */
    private function seedPar(): void
    {
        $this->gw->seedPar('E1', [
            ['id' => 90340, 'name' => 'Peach', 'qty' => 5, 'layer' => 1, 'price' => '0.10'],
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 5, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 5, 'layer' => 1, 'price' => '0.11'],
        ]);
    }

    // ── code assignment (pure) ─────────────────────────────────────────────

    public function test_codes_are_layer_times_ten_plus_position_by_citybox_id_and_idempotent(): void
    {
        $lines = collect([
            ChillerStockLine::fromApi(['product_id' => '90340', 'name' => 'c', 'quantity' => '5', 'layer' => '1']),
            ChillerStockLine::fromApi(['product_id' => '90338', 'name' => 'a', 'quantity' => '5', 'layer' => '1']),
            ChillerStockLine::fromApi(['product_id' => '89925', 'name' => 'coke', 'quantity' => '4', 'layer' => '3']),
        ]);

        $codes = ChillerPlanogram::assignCodes($lines);

        $this->assertSame(11, $codes[90338]['code']); // lowest id first
        $this->assertSame(12, $codes[90340]['code']);
        $this->assertSame(31, $codes[89925]['code']);
        $this->assertSame(4, $codes[89925]['par']);
        $again = ChillerPlanogram::assignCodes($lines->reverse()->values());
        ksort($codes);
        ksort($again);
        $this->assertSame($codes, $again); // order-independent (same codes; key order is irrelevant)
    }

    // ── mirror mapping ─────────────────────────────────────────────────────

    public function test_planogram_sync_creates_a_read_only_mirror_mapping_bound_to_the_vend(): void
    {
        $product = Product::create(['code' => 'KSF-P', 'name' => 'KSF Peach']);
        CityboxProduct::create(['citybox_product_id' => 90340, 'name' => 'Peach', 'product_id' => $product->id, 'first_seen_at' => now()]);
        $this->seedPar();

        $codes = app(ChillerPlanogram::class)->sync($this->vend);
        $this->vend->refresh();

        $mapping = ProductMapping::withoutGlobalScopes()->find($this->vend->product_mapping_id);
        $this->assertNotNull($mapping);
        $this->assertSame('smart_chiller', $mapping->machine_type);
        // product_mapping_items.product_id is NOT NULL (prod schema): only the
        // MAPPED SKU has an item; the two unmapped ones get channel codes (and
        // therefore vend_channels rows via the frame) but no mapping item yet.
        $this->assertSame(1, $mapping->productMappingItems()->count());
        $item = $mapping->productMappingItems()->where('channel_code', (string) $codes[90340]['code'])->first();
        $this->assertSame($product->id, $item->product_id);
        $this->assertEqualsWithDelta(0.10, (float) $item->server_amount, 0.001); // accessor gives dollars
        $this->assertCount(3, $codes); // all three still have codes
        $this->assertSame(3, $mapping->basket_layout_json[0]['positions']); // layer 1 has 3 positions
        $this->assertSame(0, $mapping->basket_layout_json[4]['positions']); // layer 5 empty
    }

    public function test_par_only_new_sku_gets_its_product_and_mapping_item_in_the_same_sync(): void
    {
        // SKU first, stock second (Brian, 2026-08-20): a product added to the
        // par config BEFORE it has any stock must not wait for the hourly
        // catalog run — the planogram sync itself registers it.
        \App\Models\Operator::create(['code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1]);
        (new \Database\Seeders\CityboxOperatorSeeder)->run();
        $this->gw->seedPar('E1', [['id' => 90998, 'name' => 'Configured First', 'qty' => 4, 'layer' => 3, 'price' => '0.30']]);

        $codes = app(ChillerPlanogram::class)->sync($this->vend);

        $row = CityboxProduct::where('citybox_product_id', 90998)->first();
        $this->assertNotNull($row, 'par sync must register the SKU');
        $this->assertNotNull($row->product_id, 'and create + link its mark1 product');
        $mapping = ProductMapping::withoutGlobalScopes()->find($this->vend->fresh()->product_mapping_id);
        $item = $mapping->productMappingItems()->where('channel_code', (string) $codes[90998]['code'])->first();
        $this->assertNotNull($item, 'mapping item must exist in the SAME pass, not the next one');
        $this->assertSame($row->product_id, $item->product_id);
        $this->assertSame(31, $codes[90998]['code']); // layer 3, position 1
    }

    public function test_resync_overwrites_local_edits_and_removes_delisted_rows(): void
    {
        foreach ([90340 => 'Peach', 90338 => 'Suntory', 90339 => 'Lemon'] as $cid => $n) {
            $p = Product::create(['code' => "P{$cid}", 'name' => $n]);
            CityboxProduct::create(['citybox_product_id' => $cid, 'name' => $n, 'product_id' => $p->id, 'first_seen_at' => now()]);
        }
        $this->seedPar();
        app(ChillerPlanogram::class)->sync($this->vend);
        $mapping = ProductMapping::withoutGlobalScopes()->find($this->vend->fresh()->product_mapping_id);
        // A "local edit": someone adds a rogue item and changes a price.
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '51', 'product_id' => Product::first()->id, 'server_amount' => 9.99]);
        $mapping->productMappingItems()->where('channel_code', '11')->update(['server_amount' => 99900]);

        // Their portal drops one product
        $this->gw->seedPar('E1', [['id' => 90340, 'name' => 'Peach', 'qty' => 5, 'layer' => 1, 'price' => '0.10']]);
        app(ChillerPlanogram::class)->sync($this->vend);

        $items = $mapping->fresh()->productMappingItems;
        $this->assertSame(1, $items->count());           // rogue 51 + delisted rows gone
        $this->assertSame('11', $items->first()->channel_code); // sole remaining product → position 1
        $this->assertEqualsWithDelta(0.10, (float) $items->first()->server_amount, 0.001); // price restored from their side
    }

    // ── frame adapter (pure) ───────────────────────────────────────────────

    public function test_adapter_builds_channel_rows_with_par_as_capacity(): void
    {
        $stock = collect([ChillerStockLine::fromApi(['product_id' => '90340', 'name' => 'x', 'quantity' => '1', 'layer' => '1', 'price' => '0.10', 'active_price' => '0.08'])]);
        $frame = (new ChannelFrameAdapter)->toFrame($stock, [90340 => ['code' => 11, 'par' => 5, 'layer' => 1]], 'B');

        $this->assertSame([['channel_code' => 11, 'qty' => 1, 'capacity' => 5, 'amount' => 8, 'amount2' => 10, 'error_code' => 0]], $frame->channels);
        $this->assertSame('B', $frame->toArray()['label']);
    }

    public function test_adapter_ignores_products_not_in_planogram(): void
    {
        $stock = collect([ChillerStockLine::fromApi(['product_id' => '77777', 'name' => 'stranger', 'quantity' => '3', 'layer' => '1'])]);
        $this->assertTrue((new ChannelFrameAdapter)->toFrame($stock, [90340 => ['code' => 11, 'par' => 5, 'layer' => 1]])->isEmpty());
    }

    // ── end to end: poll → real vend_channels rows ─────────────────────────

    public function test_poll_creates_vend_channels_with_qty_capacity_amount_and_product(): void
    {
        $product = Product::create(['code' => 'KSF-P', 'name' => 'KSF Peach']);
        CityboxProduct::create(['citybox_product_id' => 90340, 'name' => 'Peach', 'product_id' => $product->id, 'first_seen_at' => now()]);
        $this->seedPar();
        $this->gw->seedStock('E1', [
            ['id' => 90340, 'name' => 'Peach', 'qty' => 1, 'layer' => 1, 'price' => '0.10'],
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 0, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 0, 'layer' => 1, 'price' => '0.11'],
        ]);

        app(CityboxOpenapiSync::class)->syncAll(); // jobs run sync in tests

        $channels = VendChannel::where('vend_id', $this->vend->id)->orderBy('code')->get();
        $this->assertCount(3, $channels);
        $peach = $channels->firstWhere('code', 13); // 90340 is the highest id → position 3
        $this->assertSame(1, $peach->qty);
        $this->assertSame(5, $peach->capacity);
        $this->assertSame(10, $peach->amount);
        $this->assertSame($product->id, $peach->product_id); // stamped by syncChannelsByVend from the mirror
        $this->assertTrue((bool) $peach->is_active);
        $this->assertNull($channels->firstWhere('code', 11)->product_id); // unmapped SKU: channel exists, no product
    }

    public function test_second_poll_updates_qty_in_place_no_duplicate_channels(): void
    {
        $this->seedPar();
        $this->gw->seedStock('E1', [['id' => 90340, 'name' => 'Peach', 'qty' => 3, 'layer' => 1]]);
        app(CityboxOpenapiSync::class)->syncAll();
        $this->gw->seedStock('E1', [['id' => 90340, 'name' => 'Peach', 'qty' => 2, 'layer' => 1]]);
        app(CityboxOpenapiSync::class)->syncAll();

        $this->assertSame(1, VendChannel::where('vend_id', $this->vend->id)->count());
        $this->assertSame(2, VendChannel::where('vend_id', $this->vend->id)->first()->qty);
    }

    public function test_sku_added_mid_hour_gets_product_channel_and_qty_on_the_next_poll(): void
    {
        // Brian, 2026-08-20: a SKU added in their portal between hourly catalog
        // runs must not wait for the 1 h planogram cache — the 3-min poll sees
        // an unknown line, re-mirrors the planogram, and the frame includes it.
        \App\Models\Operator::create(['code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1]);
        (new \Database\Seeders\CityboxOperatorSeeder)->run();
        $this->seedPar();
        $this->gw->seedStock('E1', [['id' => 90340, 'name' => 'Peach', 'qty' => 3, 'layer' => 1]]);
        app(CityboxOpenapiSync::class)->syncAll(); // planogram now cached for 1 h

        // They add a brand-new SKU (never in our catalog) to the device.
        $this->gw->seedPar('E1', [
            ['id' => 90340, 'name' => 'Peach', 'qty' => 5, 'layer' => 1, 'price' => '0.10'],
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 5, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 5, 'layer' => 1, 'price' => '0.11'],
            ['id' => 90999, 'name' => 'Brand New Tea', 'qty' => 6, 'layer' => 2, 'price' => '0.20'],
        ]);
        $this->gw->seedStock('E1', [
            ['id' => 90340, 'name' => 'Peach', 'qty' => 3, 'layer' => 1],
            ['id' => 90999, 'name' => 'Brand New Tea', 'qty' => 6, 'layer' => 2, 'price' => '0.20'],
        ]);
        app(CityboxOpenapiSync::class)->syncAll(); // still within the cache TTL

        $ch = VendChannel::where('vend_id', $this->vend->id)->where('code', 21)->first();
        $this->assertNotNull($ch, 'new SKU must get a channel on the very next poll');
        $this->assertSame(6, $ch->qty);
        $this->assertSame(6, $ch->capacity);
        // And its mark1 product exists + is linked (created by the same poll).
        $row = \App\Models\CityboxProduct::where('citybox_product_id', 90999)->first();
        $this->assertNotNull($row->product_id);
        $this->assertSame($row->product_id, $ch->product_id);
    }

    public function test_pull_refreshes_planogram_immediately_bypassing_the_hourly_cache(): void
    {
        $this->seedPar();
        $this->gw->seedStock('E1', [['id' => 90340, 'name' => 'Peach', 'qty' => 3, 'layer' => 1]]);
        app(CityboxOpenapiSync::class)->syncAll();
        // Their portal raises Peach's par 5→8 (same 3 SKUs, so codes are stable:
        // Peach = highest id = position 3 = code 13). A plain poll keeps the
        // hourly-cached 5; Pull must re-mirror and see 8.
        $this->gw->seedPar('E1', [
            ['id' => 90340, 'name' => 'Peach', 'qty' => 8, 'layer' => 1, 'price' => '0.10'],
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 5, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 5, 'layer' => 1, 'price' => '0.11'],
        ]);
        app(CityboxOpenapiSync::class)->syncAll();
        $this->assertSame(5, VendChannel::where('vend_id', $this->vend->id)->where('code', 13)->first()->capacity);

        app(CityboxOpenapiSync::class)->pull($this->vend);
        $this->assertSame(8, VendChannel::where('vend_id', $this->vend->id)->where('code', 13)->first()->capacity);
    }

    public function test_planogram_endpoint_returns_five_layers_top_first_with_channels_and_totals(): void
    {
        $product = Product::create(['code' => 'KSF-P', 'name' => 'KSF Peach']);
        CityboxProduct::create(['citybox_product_id' => 90340, 'name' => 'Peach', 'product_id' => $product->id, 'img_url' => 'https://cdn/p.png', 'first_seen_at' => now()]);
        $this->seedPar();
        $this->gw->seedStock('E1', [
            ['id' => 90340, 'name' => 'Peach', 'qty' => 1, 'layer' => 1, 'price' => '0.10'],
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 0, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 0, 'layer' => 1, 'price' => '0.11'],
        ]);
        app(CityboxOpenapiSync::class)->syncAll();
        $user = \App\Models\User::factory()->create();

        $r = $this->actingAs($user)->getJson("/vends/{$this->vend->id}/citybox-planogram")->assertOk()->json();

        $this->assertCount(5, $r['layers']);
        $this->assertSame(5, $r['layers'][0]['layer']); // top of rack first
        $this->assertSame(1, $r['layers'][4]['layer']);
        $this->assertCount(3, $r['layers'][4]['channels']);
        $this->assertSame([1, 15], [$r['total_qty'], $r['total_capacity']]);
        $this->assertSame(2, $r['unmapped_count']);
        $peach = collect($r['layers'][4]['channels'])->firstWhere('code', 13);
        $this->assertSame('KSF Peach', $peach['product']['name']);
        $this->assertSame('https://cdn/p.png', $peach['thumbnail']);
        $this->assertTrue($peach['mapped']);
    }

    public function test_planogram_endpoint_is_403_for_non_chiller(): void
    {
        $vm = Vend::create(['code' => 9501, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1]);
        $this->actingAs(\App\Models\User::factory()->create())->getJson("/vends/{$vm->id}/citybox-planogram")->assertForbidden();
    }

    public function test_no_planogram_means_no_channel_push_but_poll_still_recorded(): void
    {
        Queue::fake(); // fake everything here: we only assert nothing was pushed
        // par endpoint returns nothing → codes [] → no frame
        $this->gw->seedStock('E1', [['id' => 90340, 'name' => 'Peach', 'qty' => 3, 'layer' => 1]]);
        app(CityboxOpenapiSync::class)->syncAll();

        Queue::assertNotPushed(\App\Jobs\Vend\SyncVendChannels::class);
        $this->assertSame(1, \App\Models\CityboxInventoryPoll::count());
    }
}
