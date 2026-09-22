<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Jobs\PublishMqtt;
use App\Jobs\Vend\SaveVendChannelsJson;
use App\Jobs\Vend\SyncVendChannelErrorLog;
use App\Models\Customer;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\OpsJobItemChannel;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\Citybox\CityboxOpenapiSync;
use App\Services\Freezer\FreezerChannelSync;
use App\Services\Stock\SkuPlanogram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\Citybox\ChillerMapping;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

/**
 * Smart Freezer + Smart Chiller stock is keyed by SKU (Brian, 2026-09-22): the
 * vend_channels row IS the product, the channel code (+ one-letter suffix) is
 * only where the driver puts it. So a SKU that moves between codes keeps its
 * row and its qty, two SKUs may share one number as 101A / 101B, and a
 * changeover diffs product sets, never slots.
 */
class SkuStockIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // SyncVendChannels runs inline (queue=sync); its Redis-unique children are faked.
        Queue::fake([SyncVendChannelErrorLog::class, SaveVendChannelsJson::class, PublishMqtt::class]);
    }

    // ── freezer ────────────────────────────────────────────────────────────

    /** @return array{0:Vend,1:ProductMapping,2:Product,3:Product} */
    private function freezer(array $layout): array
    {
        $a = Product::create(['code' => 'F-A', 'name' => 'Cornetto', 'freezer_slot_qty' => 24, 'is_active' => true]);
        $b = Product::create(['code' => 'F-B', 'name' => 'Magnum', 'freezer_slot_qty' => 12, 'is_active' => true]);
        SellingPrice::create(['product_id' => $a->id, 'type' => SellingPrice::TYPE_2, 'amount' => 3.00]);
        SellingPrice::create(['product_id' => $b->id, 'type' => SellingPrice::TYPE_2, 'amount' => 4.50]);
        $mapping = $this->freezerMapping('Layout A', $layout, ['A' => $a, 'B' => $b]);
        $customer = Customer::create(['name' => 'Site', 'code' => 'FS1', 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => SellingPrice::TYPE_2]);
        $vend = Vend::create([
            'code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1,
            'vend_model_id' => 1, 'customer_id' => $customer->id, 'product_mapping_id' => $mapping->id, 'is_using_server_price' => true,
        ]);

        return [$vend, $mapping, $a, $b];
    }

    /** @param  array<string,string>  $layout  code => 'A'|'B' */
    private function freezerMapping(string $name, array $layout, array $products): ProductMapping
    {
        $mapping = ProductMapping::create(['name' => $name, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_smart' => true, 'is_active' => true, 'operator_id' => 1]);
        foreach ($layout as $code => $key) {
            ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => (string) $code, 'product_id' => $products[$key]->id]);
        }

        return $mapping;
    }

    private function rows(Vend $vend): \Illuminate\Support\Collection
    {
        return VendChannel::where('vend_id', $vend->id)->orderBy('code')->orderBy('suffix')->get();
    }

    public function test_a_freezer_sku_that_moves_basket_keeps_its_row_and_its_ledger(): void
    {
        [$vend, $mapping, $a, $b] = $this->freezer(['11' => 'A', '21' => 'B']);
        app(FreezerChannelSync::class)->sync($vend);
        $rowA = $this->rows($vend)->firstWhere('product_id', $a->id);
        $rowA->update(['qty' => 7]);

        // Ops swap the two baskets in the mapping: A → 21, B → 11.
        $mapping->productMappingItems()->where('channel_code', '11')->update(['product_id' => $b->id]);
        $mapping->productMappingItems()->where('channel_code', '21')->update(['product_id' => $a->id]);
        app(FreezerChannelSync::class)->sync($vend->fresh());

        $rows = $this->rows($vend);
        $this->assertCount(2, $rows, 'two SKUs, two rows — no retire-and-recreate');
        $movedA = $rows->firstWhere('product_id', $a->id);
        $this->assertSame($rowA->id, $movedA->id, 'the SKU keeps its row');
        $this->assertSame(21, (int) $movedA->code);
        $this->assertSame(7, (int) $movedA->qty, 'the topup ledger rides along with the SKU');
        $this->assertSame(11, (int) $rows->firstWhere('product_id', $b->id)->code, 'a label swap does not trip the unique index');
        $this->assertSame(300, (int) $movedA->amount, "the Site's RP2, in cents");
    }

    public function test_a_freezer_sku_dropped_from_the_planogram_is_retired_with_its_qty_and_returns_with_it(): void
    {
        [$vend, $mapping, $a, $b] = $this->freezer(['11' => 'A', '21' => 'B']);
        app(FreezerChannelSync::class)->sync($vend);
        $this->rows($vend)->firstWhere('product_id', $b->id)->update(['qty' => 3]);

        $mapping->productMappingItems()->where('channel_code', '21')->delete();
        app(FreezerChannelSync::class)->sync($vend->fresh());
        $rowB = $this->rows($vend)->firstWhere('product_id', $b->id);
        $this->assertFalse((bool) $rowB->is_active, 'gone from the planogram: inactive, not deleted');
        $this->assertSame(3, (int) $rowB->qty, 'its qty is kept for the return');
        $this->assertSame(1, $vend->fresh()->vendChannels()->count());

        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '31', 'product_id' => $b->id]);
        app(FreezerChannelSync::class)->sync($vend->fresh());
        $back = $this->rows($vend)->firstWhere('product_id', $b->id);
        $this->assertSame($rowB->id, $back->id);
        $this->assertTrue((bool) $back->is_active);
        $this->assertSame([31, 3], [(int) $back->code, (int) $back->qty], 'reactivated on its new basket with its ledger');
    }

    public function test_a_sku_on_two_codes_is_one_row_with_the_summed_capacity_and_the_first_label(): void
    {
        [$vend, $mapping, $a] = $this->freezer(['11' => 'A', '31' => 'A']);
        $mapping->productMappingItems()->where('channel_code', '31')->update(['capacity_override' => 10]);

        $this->assertSame(1, app(FreezerChannelSync::class)->sync($vend));

        $rows = $this->rows($vend);
        $this->assertCount(1, $rows);
        $this->assertSame([11, 34], [(int) $rows[0]->code, (int) $rows[0]->capacity], '24 (product default) + 10 (mapping override)');
        $slot = app(SkuPlanogram::class)->forVend($vend)[$a->id];
        $this->assertSame(['11', '31'], $slot->labels);
    }

    // ── chiller ────────────────────────────────────────────────────────────

    private function chiller(): array
    {
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        $gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $gw);
        $vend = Vend::create(['code' => 9700, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E7', 'is_active' => 1, 'operator_id' => 1]);
        $gw->seedDevice('E7');

        return [$vend, $gw];
    }

    public function test_two_chiller_skus_share_one_number_as_101a_and_101b(): void
    {
        [$vend, $gw] = $this->chiller();
        ChillerMapping::bind($vend, ['101A' => [90338, 4], '101B' => [90339, 6], '203' => [90340, 2]]);
        $gw->seedStock('E7', [
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 3, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 5, 'layer' => 1, 'price' => '0.11'],
        ]);

        app(CityboxOpenapiSync::class)->syncAll();

        $rows = $this->rows($vend)->where('is_active', true)->values();
        $this->assertSame(['101A', '101B', '203'], $rows->map(fn ($r) => $r->label)->all());
        $this->assertSame([3, 5, 0], $rows->map(fn ($r) => (int) $r->qty)->all(), 'each SKU carries its own live qty');
        $this->assertSame([4, 6, 2], $rows->map(fn ($r) => (int) $r->capacity)->all());
        $this->assertSame(2, $rows->where('code', 101)->count(), 'same number, two rows, distinct suffixes');
    }

    public function test_a_chiller_sku_on_two_codes_is_one_row_holding_the_whole_live_qty(): void
    {
        [$vend, $gw] = $this->chiller();
        ChillerMapping::bind($vend, [101 => [90338, 5], 103 => [90338, 5], 201 => [90339, 0]]);
        $gw->seedStock('E7', [['id' => 90338, 'name' => 'Suntory', 'qty' => 7, 'layer' => 1, 'price' => '0.12']]);

        app(CityboxOpenapiSync::class)->syncAll();

        $rows = $this->rows($vend)->where('is_active', true)->values();
        $this->assertSame([101, 201], $rows->map(fn ($r) => (int) $r->code)->all());
        $this->assertSame([7, 10], [(int) $rows[0]->qty, (int) $rows[0]->capacity], 'no split across facings: one SKU, one number');
    }

    public function test_mapping_validation_normalises_letters_refuses_a_split_and_whole_position_together_and_keeps_freezers_numeric(): void
    {
        [$vend] = $this->chiller();
        $mapping = ChillerMapping::bind($vend, [101 => [90338, 5]]);
        $lemon = ChillerMapping::product(90339, 6);
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('read product-mappings', 'web'));

        $this->actingAs($user)->post("/product-mappings/{$mapping->id}/items/create", ['channel_code' => ' 102a', 'product_id' => $lemon->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_mapping_items', ['product_mapping_id' => $mapping->id, 'channel_code' => '102A']);

        $this->actingAs($user)->post("/product-mappings/{$mapping->id}/items/create", ['channel_code' => '102', 'product_id' => $lemon->id])
            ->assertSessionHasErrors('channel_code'); // 102 vs 102A: whole or split, not both
        $this->actingAs($user)->post("/product-mappings/{$mapping->id}/items/create", ['channel_code' => '101B', 'product_id' => $lemon->id])
            ->assertSessionHasErrors('channel_code'); // 101 is a whole position already
        $this->actingAs($user)->post("/product-mappings/{$mapping->id}/items/create", ['channel_code' => '601', 'product_id' => $lemon->id])
            ->assertSessionHasErrors('channel_code'); // out of range

        [$freezer, $fMapping] = $this->freezer(['11' => 'A']);
        $this->actingAs($user)->post("/product-mappings/{$fMapping->id}/items/create", ['channel_code' => '21A', 'product_id' => Product::where('code', 'F-B')->value('id')])
            ->assertSessionHasErrors('channel_code'); // the freezer APK sends the slot as an int
    }

    // ── changeover by SKU set ──────────────────────────────────────────────

    public function test_a_freezer_changeover_stages_only_arriving_skus_and_returns_only_leaving_ones(): void
    {
        Permission::findOrCreate('update operations', 'web');
        [$vend, $current, $a, $b] = $this->freezer(['11' => 'A', '21' => 'B']);
        app(FreezerChannelSync::class)->sync($vend);
        $this->rows($vend)->firstWhere('product_id', $a->id)->update(['qty' => 6]);
        $this->rows($vend)->firstWhere('product_id', $b->id)->update(['qty' => 2]);
        $c = Product::create(['code' => 'F-C', 'name' => 'Paddle Pop', 'freezer_slot_qty' => 30, 'is_active' => true]);
        // Layout B: A moves to 51, B leaves, C arrives on 21.
        $upcoming = $this->freezerMapping('Layout B', ['51' => 'A', '21' => 'C'], ['A' => $a, 'C' => $c]);
        $vend->forceFill(['upcoming_product_mapping_id' => $upcoming->id])->save();

        $driver = User::factory()->create();
        $driver->givePermissionTo('update operations');
        $job = OpsJob::create(['code' => 900200, 'date' => now()->toDateString(), 'status' => 1, 'delivered_by' => $driver->id, 'operator_id' => 1]);
        $item = OpsJobItem::create(['ops_job_id' => $job->id, 'vend_id' => $vend->id, 'customer_id' => $vend->customer_id, 'status' => OpsJob::STATUS_PENDING]);
        foreach ($vend->fresh()->vendChannels as $vc) {
            OpsJobItemChannel::create(['ops_job_id' => $job->id, 'ops_job_item_id' => $item->id, 'vend_channel_id' => $vc->id, 'vend_channel_code' => $vc->code, 'vend_code' => $vend->code, 'product_id' => $vc->product_id, 'qty' => $vc->qty, 'capacity' => $vc->capacity, 'picked_qty' => 0]);
        }

        $this->actingAs($driver)->post("/ops-jobs/items/{$item->id}/update/stock-action", ['stock_action_type' => 'implement_new_mapping'])->assertSessionHasNoErrors();

        $staged = $item->opsJobItemChannels()->where('is_upcoming_product', true)->get();
        $this->assertSame([$c->id], $staged->pluck('product_id')->map(fn ($id) => (int) $id)->all(), 'only the ARRIVING SKU is staged — A merely moved');
        $this->assertSame(21, (int) $staged[0]->vend_channel_code);
        $this->assertSame(30, (int) $staged[0]->capacity);
        $rowC = VendChannel::where('vend_id', $vend->id)->where('product_id', $c->id)->first();
        $this->assertFalse((bool) $rowC->is_active);
        $this->assertLessThan(0, (int) $rowC->code, 'no position until the swap: 21 still belongs to B');
        $rowsByProduct = $item->opsJobItemChannels()->where('is_upcoming_product', false)->get()->keyBy('product_id');
        $this->assertSame(0, (int) $rowsByProduct[$a->id]->picked_qty, 'A stays: nothing cleared off');
        $this->assertSame(-2, (int) $rowsByProduct[$b->id]->picked_qty, 'B leaves: cleared off');

        // The driver completes the visit.
        $item->update(['status' => OpsJob::STATUS_PICKED]);
        $payload = [];
        foreach ($item->opsJobItemChannels()->get() as $ch) {
            $refill = $ch->is_upcoming_product ? 12 : ((int) $ch->product_id === $a->id ? 4 : 0);
            $payload[] = ['id' => $ch->id, 'qty' => (int) $ch->qty, 'refill' => $refill, 'capacity' => (int) $ch->capacity];
        }
        $this->actingAs($driver)->post("/ops-jobs/items/{$item->id}/confirm", ['channels' => $payload])->assertSessionHasNoErrors();

        $this->assertSame($upcoming->id, $vend->fresh()->product_mapping_id);
        $final = $item->opsJobItemChannels()->where('is_upcoming_product', false)->get()->keyBy('product_id');
        $this->assertSame(4, (int) $final[$a->id]->actual_qty, 'a moved SKU is topped up, never auto-returned');
        $this->assertSame(-2, (int) $final[$b->id]->actual_qty, 'a leaving SKU is returned in full');

        $rows = $this->rows($vend)->where('is_active', true)->values();
        $this->assertSame(['21', '51'], $rows->map(fn ($r) => $r->label)->all());
        $this->assertSame($c->id, (int) $rows[0]->product_id, 'C took 21 once B released it');
        $this->assertSame(6, (int) $rows->firstWhere('product_id', $a->id)->qty, "A's ledger survived the move to 51");
        $this->assertFalse((bool) VendChannel::where('vend_id', $vend->id)->where('product_id', $b->id)->value('is_active'));
    }
}
