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

    public function test_qty_in_chillers_sums_only_active_channels_on_smart_chiller_vends(): void
    {
        $p = Product::create(['code' => 'CB9', 'name' => 'Peach', 'warehouse_qty_source' => 'ledger']);
        $chiller = \App\Models\Vend::create(['code' => 9801, 'machine_type' => 'smart_chiller', 'citybox_equipment_id' => 'Q1', 'is_active' => 1]);
        $vm = \App\Models\Vend::create(['code' => 9802, 'machine_type' => 'vending_machine', 'is_active' => 1]);
        \App\Models\VendChannel::create(['vend_id' => $chiller->id, 'code' => 11, 'qty' => 4, 'capacity' => 5, 'amount' => 10, 'product_id' => $p->id, 'is_active' => 1, 'error_rate_json' => []]);
        \App\Models\VendChannel::create(['vend_id' => $chiller->id, 'code' => 12, 'qty' => 9, 'capacity' => 5, 'amount' => 10, 'product_id' => $p->id, 'is_active' => 0, 'error_rate_json' => []]); // inactive: excluded
        \App\Models\VendChannel::create(['vend_id' => $vm->id, 'code' => 11, 'qty' => 7, 'capacity' => 5, 'amount' => 10, 'product_id' => $p->id, 'is_active' => 1, 'error_rate_json' => []]);      // vending machine: excluded

        $this->assertSame(4, $p->qtyInChillers());
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

    private function plannerUser(): User
    {
        foreach (['read product-availability'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $op = \App\Models\Operator::create(['code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1]);
        $u = User::factory()->create(['operator_id' => $op->id]);
        $u->givePermissionTo(['read products', 'update products', 'read product-availability']);

        return $u;
    }

    public function test_availability_page_shows_ledger_qty_for_manual_products_and_cms_qty_for_the_rest(): void
    {
        $u = $this->plannerUser();
        config(['app.cms_url' => 'https://cms.test']);
        \Illuminate\Support\Facades\Http::fake(['cms.test/*' => \Illuminate\Support\Facades\Http::response([['code' => 'VM1', 'qty' => 40]])]);

        $vm = Product::create(['code' => 'VM1', 'name' => 'Vending coke', 'operator_id' => $u->operator_id, 'is_available' => 1]);
        $cb = Product::create(['code' => '89925', 'name' => 'Chiller coke', 'operator_id' => $u->operator_id, 'is_available' => 1, 'warehouse_qty_source' => 'ledger']);
        DB::table('product_movements')->insert(['product_id' => $cb->id, 'type' => ProductMovement::TYPE_INCOMING, 'qty' => 12, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($u)->get('/products/availability?operators[]=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vend/ProductAvailability')
                ->where('products.data', fn ($rows) => collect($rows)->firstWhere('code', 'VM1')['qty_available_pcs_api'] === 40
                    && collect($rows)->firstWhere('code', '89925')['qty_available_pcs_api'] === 12
                    && collect($rows)->firstWhere('code', '89925')['net_available_qty_pcs_api'] === 12
                    && collect($rows)->firstWhere('code', '89925')['warehouse_qty_source'] === 'ledger'));
    }

    public function test_movements_page_filters_by_qty_source_and_exposes_it_per_row(): void
    {
        $u = $this->plannerUser();
        Product::create(['code' => 'VM1', 'name' => 'Vending coke', 'operator_id' => $u->operator_id]);
        Product::create(['code' => '89925', 'name' => 'Chiller coke', 'operator_id' => $u->operator_id, 'warehouse_qty_source' => 'ledger']);

        $this->actingAs($u)->get('/products/movements?operators[]=all&warehouse_qty_source=ledger')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vend/ProductMovement')
                ->where('filters.warehouse_qty_source', 'ledger')
                ->where('products.data', fn ($rows) => count($rows) === 1 && $rows[0]['code'] === '89925' && $rows[0]['warehouse_qty_source'] === 'ledger'));

        $this->actingAs($u)->get('/products/movements?operators[]=all&warehouse_qty_source=all')
            ->assertInertia(fn ($page) => $page->where('products.data', fn ($rows) => count($rows) === 2));
    }
}
