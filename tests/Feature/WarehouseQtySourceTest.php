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
        // HIPL must be operator id 1: OperatorProductFilterScope keys "sees every
        // operator" on id 1 (as in prod), and RefreshDatabase does not reset autoincrement.
        $op = \App\Models\Operator::create(['code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1]);
        DB::table('operators')->where('id', $op->id)->update(['id' => 1]);
        $u = User::factory()->create(['operator_id' => 1]);
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

    public function test_movements_page_lists_only_self_system_products_when_cms_is_connected(): void
    {
        $u = $this->plannerUser();
        Product::create(['code' => 'VM1', 'name' => 'Vending coke', 'operator_id' => $u->operator_id]);
        Product::create(['code' => '89925', 'name' => 'Chiller coke', 'operator_id' => $u->operator_id, 'warehouse_qty_source' => 'ledger']);

        config(['app.cms_url' => 'https://cms.test']);
        $this->actingAs($u)->get('/products/movements?operators[]=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vend/ProductMovement')
                ->where('products.data', fn ($rows) => count($rows) === 1 && $rows[0]['code'] === '89925'));

        // No CMS on this domain: the ledger is everyone's truth — every product listed.
        config(['app.cms_url' => null]);
        $this->actingAs($u)->get('/products/movements?operators[]=all')
            ->assertInertia(fn ($page) => $page->where('products.data', fn ($rows) => count($rows) === 2));
    }

    public function test_shared_props_expose_cms_and_citybox_flags_from_config(): void
    {
        $u = $this->plannerUser();

        // CMS + CityBox on (the sys domain): product forms offer the source
        // choice and may use CityBox wording.
        config(['app.cms_url' => 'https://cms.test', 'citybox.openapi.enabled' => true]);
        $this->actingAs($u)->get('/products/movements')
            ->assertInertia(fn ($page) => $page->where('isCmsUrlSet', true)->where('isCityboxEnabled', true));

        // Neither (an LSH-style domain): no source choice, no CityBox terms.
        config(['app.cms_url' => null, 'citybox.openapi.enabled' => false]);
        $this->actingAs($u)->get('/products/movements')
            ->assertInertia(fn ($page) => $page->where('isCmsUrlSet', false)->where('isCityboxEnabled', false));
    }

    public function test_batch_incoming_lists_only_self_system_products_when_cms_is_connected(): void
    {
        $u = $this->plannerUser();
        Product::create(['code' => 'VM1', 'name' => 'Vending coke', 'operator_id' => $u->operator_id, 'is_inventory' => 1]);
        Product::create(['code' => '89925', 'name' => 'Chiller coke', 'operator_id' => $u->operator_id, 'is_inventory' => 1, 'warehouse_qty_source' => 'ledger']);
        // Inactive ledger product: never offered for incoming, on any domain.
        Product::create(['code' => '89999', 'name' => 'Delisted chiller coke', 'operator_id' => $u->operator_id, 'is_inventory' => 1, 'is_active' => 0, 'warehouse_qty_source' => 'ledger']);

        config(['app.cms_url' => 'https://cms.test']);
        $this->actingAs($u)->get('/products/movements/batch-incoming')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ProductMovement/BatchIncoming')
                ->where('products.data', fn ($rows) => count($rows) === 1 && $rows[0]['code'] === '89925'));

        // No CMS on this domain: every inventory product takes incoming here.
        config(['app.cms_url' => null]);
        $this->actingAs($u)->get('/products/movements/batch-incoming')
            ->assertInertia(fn ($page) => $page->where('products.data', fn ($rows) => count($rows) === 2));
    }

    public function test_availability_export_shows_the_ledger_qty_for_manual_products(): void
    {
        $u = $this->plannerUser();
        config(['app.cms_url' => 'https://cms.test']);
        \Illuminate\Support\Facades\Http::fake(['cms.test/*' => \Illuminate\Support\Facades\Http::response([['code' => 'VM1', 'qty' => 40]])]);
        \Maatwebsite\Excel\Facades\Excel::fake();

        Product::create(['code' => 'VM1', 'name' => 'Vending coke', 'operator_id' => $u->operator_id, 'is_available' => 1]);
        $cb = Product::create(['code' => '89925', 'name' => 'Chiller coke', 'operator_id' => $u->operator_id, 'is_available' => 1, 'warehouse_qty_source' => 'ledger']);
        DB::table('product_movements')->insert(['product_id' => $cb->id, 'type' => ProductMovement::TYPE_INCOMING, 'qty' => 12, 'created_at' => now(), 'updated_at' => now()]);

        \Illuminate\Support\Carbon::setTestNow('2026-08-20 10:00:00');
        $fileName = 'Product_Availability_'.str_replace('-', '', now()->addDay()->toDateString()).'_'.now()->format('His').'.xlsx';

        $this->actingAs($u)->get('/products/availability/export-excel?operators[]=all')->assertOk();

        \Maatwebsite\Excel\Facades\Excel::assertDownloaded(
            $fileName,
            function (\App\Exports\ProductAvailabilityExport $export) {
                $rows = collect($export->collection());
                if ($rows->isEmpty()) {
                    return false;
                }
                $ledger = $rows->firstWhere('code', '89925');
                $cms = $rows->firstWhere('code', 'VM1');

                return $ledger && (int) $ledger->qty_available_pcs_api === 12
                    && (int) $ledger->net_available_qty_pcs_api === 12
                    && $cms && (int) $cms->qty_available_pcs_api === 40;
            }
        );
    }

    public function test_ledger_page_defaults_to_all_operators_and_warehouse_log_honours_all(): void
    {
        $u = $this->plannerUser(); // HIPL
        $cb = \App\Models\Operator::create(['code' => 'CB', 'name' => 'Citybox', 'country_id' => 1]);
        Product::create(['code' => '89925', 'name' => 'Chiller coke', 'operator_id' => $cb->id, 'warehouse_qty_source' => 'ledger']);
        config(['app.cms_url' => 'https://cms.test']);

        // No operators param at all: the CB product (not in the HIPL group) is listed.
        $this->actingAs($u)->get('/products/movements')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('products.data', fn ($rows) => count($rows) === 1 && $rows[0]['code'] === '89925'));

        // Warehouse Log with operators[]=all must not be empty (whereIn 'all' used to match nothing).
        ProductMovement::create(['product_id' => Product::withoutGlobalScopes()->where('code', '89925')->value('id'), 'type' => ProductMovement::TYPE_INCOMING, 'qty' => 5, 'operator_id' => $cb->id, 'created_at' => now()]);
        $this->actingAs($u)->get('/products/movements/tracking?operators[]=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ProductMovement/TrackingDetails')
                ->where('movements.data', fn ($rows) => collect($rows)->contains(fn ($r) => (int) ($r['qty'] ?? 0) === 5)));
    }
}
