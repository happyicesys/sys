<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Enums\Citybox\MovementType;
use App\Models\CityboxInventoryPoll;
use App\Models\CityboxProduct;
use App\Models\CityboxStockMovement;
use App\Models\Product;
use App\Models\Vend;
use App\Services\Citybox\CityboxOpenapiSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

class CityboxStockPollTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
        $this->vend = Vend::create(['code' => 9400, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E1', 'is_active' => 1]);
        $this->gw->seedDevice('E1');
    }

    private function stock(int $peach, int $suntory = 0): void
    {
        $this->gw->seedStock('E1', [
            ['id' => 90340, 'name' => 'Peach', 'qty' => $peach, 'layer' => 1, 'price' => '0.10'],
            ['id' => 90338, 'name' => 'Suntory', 'qty' => $suntory, 'layer' => 1, 'price' => '0.12'],
        ]);
    }

    public function test_first_poll_writes_a_row_and_no_movements(): void
    {
        $this->stock(3, 1);

        app(CityboxOpenapiSync::class)->syncAll();

        $poll = CityboxInventoryPoll::sole();
        $this->assertSame($this->vend->id, $poll->vend_id);
        $this->assertTrue($poll->online);
        $this->assertSame(2, $poll->products_seen);
        $this->assertSame(4, $poll->total_qty);
        $this->assertSame(3, $poll->snapshot_json['p90340']['quantity']);
        $this->assertSame(0, $poll->movements_count);
        $this->assertSame(0, CityboxStockMovement::count());
        $this->assertNotNull($poll->duration_ms);
    }

    public function test_second_poll_diffs_and_classifies_a_sale_with_denormalised_product(): void
    {
        $product = Product::create(['code' => 'KSF-P', 'name' => 'KSF Peach']);
        CityboxProduct::create(['citybox_product_id' => 90340, 'name' => 'Peach', 'product_id' => $product->id, 'first_seen_at' => now()]);
        $this->stock(3, 1);
        app(CityboxOpenapiSync::class)->syncAll();

        $this->travel(3)->minutes();
        $this->stock(2, 1); // one peach sold
        app(CityboxOpenapiSync::class)->syncAll();

        $m = CityboxStockMovement::sole();
        $this->assertSame(90340, $m->citybox_product_id);
        $this->assertSame($product->id, $m->product_id);
        $this->assertSame([3, 2, -1], [$m->qty_before, $m->qty_after, $m->delta]);
        $this->assertSame(MovementType::Sale, $m->movement_type);
        $this->assertNull($m->ops_job_item_id);
        $this->assertSame(CityboxInventoryPoll::first()->id, $m->prev_poll_id);
        $this->assertSame(1, CityboxInventoryPoll::latest('id')->first()->movements_count);
    }

    public function test_unchanged_poll_writes_no_movements(): void
    {
        $this->stock(3, 1);
        app(CityboxOpenapiSync::class)->syncAll();
        $this->travel(3)->minutes();
        app(CityboxOpenapiSync::class)->syncAll();

        $this->assertSame(2, CityboxInventoryPoll::count());
        $this->assertSame(0, CityboxStockMovement::count());
    }

    public function test_rise_without_visit_is_unknown_not_restock(): void
    {
        $this->stock(1);
        app(CityboxOpenapiSync::class)->syncAll();
        $this->travel(3)->minutes();
        $this->stock(6);
        app(CityboxOpenapiSync::class)->syncAll();

        $this->assertSame(MovementType::Unknown, CityboxStockMovement::sole()->movement_type);
    }

    public function test_failed_read_writes_error_row_keeps_snapshot_and_diffs_against_last_good_poll(): void
    {
        $this->stock(3);
        app(CityboxOpenapiSync::class)->syncAll();               // good #1: 3

        $this->travel(3)->minutes();
        $this->gw->stockErrors['E1'] = '此设备没有商品';
        $summary = app(CityboxOpenapiSync::class)->syncAll();   // failed #2
        $this->assertSame(['E1' => '此设备没有商品'], $summary['stock_errors']);
        $failed = CityboxInventoryPoll::latest('id')->first();
        $this->assertNotNull($failed->error);
        $this->assertNull($failed->snapshot_json);
        $this->assertSame(3, $this->vend->fresh()->citybox_status_json['stock']['p90340']['quantity']); // kept

        $this->travel(3)->minutes();
        unset($this->gw->stockErrors['E1']);
        $this->stock(1);
        app(CityboxOpenapiSync::class)->syncAll();               // good #3: 1 → diff vs #1 (3), NOT vs the failed row

        $m = CityboxStockMovement::sole();
        $this->assertSame([3, 1, -2], [$m->qty_before, $m->qty_after, $m->delta]);
        $this->assertSame(CityboxInventoryPoll::first()->id, $m->prev_poll_id);
    }

    public function test_product_new_to_device_produces_no_movement_only_a_catalog_row(): void
    {
        $this->gw->seedStock('E1', [['id' => 90340, 'name' => 'Peach', 'qty' => 2]]);
        app(CityboxOpenapiSync::class)->syncAll();
        $this->travel(3)->minutes();
        $this->gw->seedStock('E1', [['id' => 90340, 'name' => 'Peach', 'qty' => 2], ['id' => 89925, 'name' => 'Coke', 'qty' => 4]]);
        app(CityboxOpenapiSync::class)->syncAll();

        $this->assertSame(0, CityboxStockMovement::count());
        $this->assertNotNull(CityboxProduct::where('citybox_product_id', 89925)->first());
    }

    public function test_prune_deletes_old_polls_but_never_movements(): void
    {
        $this->stock(3);
        app(CityboxOpenapiSync::class)->syncAll();
        $this->travel(3)->minutes();
        $this->stock(2);
        app(CityboxOpenapiSync::class)->syncAll();
        CityboxInventoryPoll::query()->update(['polled_at' => now()->subDays(100)]);

        $this->artisan('citybox:prune-polls', ['--days' => 90])->assertSuccessful();

        $this->assertSame(0, CityboxInventoryPoll::count());
        $this->assertSame(1, CityboxStockMovement::count());
    }
}
