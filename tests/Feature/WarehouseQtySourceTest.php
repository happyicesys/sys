<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Enums\WarehouseQtySource;
use App\Models\CityboxProduct;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\OpsJobItemChannel;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

class WarehouseQtySourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(ChillerGateway::class, new FakeChillerGateway);
        foreach (['read products', 'update products'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    public function test_every_existing_product_defaults_to_cms_and_accessor_is_null_for_cms(): void
    {
        $p = Product::create(['code' => 'X1', 'name' => 'Legacy']);

        $this->assertSame('cms', $p->fresh()->warehouse_qty_source);
        $this->assertSame(WarehouseQtySource::Cms, $p->warehouseQtySource());
        $this->assertFalse($p->usesLedgerWarehouseQty());
        $this->assertNull($p->warehouseQty()); // cms-source: caller keeps reading the CMS figure
    }

    public function test_ledger_accessor_matches_movements_page_arithmetic(): void
    {
        $p = Product::create(['code' => 'CB1', 'name' => 'Coke', 'warehouse_qty_source' => 'ledger']);
        // incoming 10 + adjustment -2 = 8; a PICKED movement type must be ignored (page counts picks via ops jobs)
        DB::table('product_movements')->insert([
            ['product_id' => $p->id, 'type' => ProductMovement::TYPE_INCOMING, 'qty' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $p->id, 'type' => ProductMovement::TYPE_ADJUSTMENT, 'qty' => -2, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $p->id, 'type' => ProductMovement::TYPE_PICKED, 'qty' => -99, 'created_at' => now(), 'updated_at' => now()],
        ]);
        // picks: 3 on a picked job after go-live (counts), 5 on a cancelled job (ignored), 4 on a pre-go-live job (ignored)
        $mk = function (int $status, string $date, int $picked) use ($p) {
            static $seq = 0;
            $job = OpsJob::create(['date' => $date, 'status' => 1, 'code' => 900000 + (++$seq)]);
            $item = OpsJobItem::create(['ops_job_id' => $job->id, 'vend_id' => 1, 'customer_id' => 1, 'status' => $status]);
            OpsJobItemChannel::create(['ops_job_id' => $job->id, 'ops_job_item_id' => $item->id, 'product_id' => $p->id, 'picked_qty' => $picked, 'qty' => $picked, 'capacity' => 10, 'vend_channel_code' => 11, 'vend_code' => 1]);
        };
        $mk(2, '2026-08-01', 3);
        $mk(99, '2026-08-01', 5);
        $mk(2, '2025-01-01', 4);

        $this->assertSame(8 - 3, $p->warehouseQty());
    }

    public function test_mapping_a_citybox_sku_flips_the_product_to_ledger(): void
    {
        $u = User::factory()->create();
        $u->givePermissionTo(['read products', 'update products']);
        $product = Product::create(['code' => 'CB2', 'name' => 'Peach']);
        $row = CityboxProduct::create(['citybox_product_id' => 90340, 'name' => 'Peach', 'first_seen_at' => now()]);

        $this->actingAs($u)->post("/citybox/products/{$row->id}/map", ['product_id' => $product->id])->assertRedirect();

        $this->assertSame('ledger', $product->fresh()->warehouse_qty_source);
    }

    public function test_product_update_validates_and_persists_the_source(): void
    {
        $u = User::factory()->create();
        $u->givePermissionTo(['read products', 'update products']);
        $product = Product::create(['code' => 'CB3', 'name' => 'Haribo', 'operator_id' => 1]);

        $this->actingAs($u)->post("/products/{$product->id}/update", [
            'name' => 'Haribo', 'code' => 'CB3', 'operator_id' => 1, 'warehouse_qty_source' => 'bogus',
        ])->assertSessionHasErrors('warehouse_qty_source');

        $this->actingAs($u)->post("/products/{$product->id}/update", [
            'name' => 'Haribo', 'code' => 'CB3', 'operator_id' => 1, 'warehouse_qty_source' => 'ledger',
        ]);
        $this->assertSame('ledger', $product->fresh()->warehouse_qty_source);
    }
}
