<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\Customer;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

/**
 * The three places a Smart Chiller must not be treated as a vending machine:
 *  1. Setting/Edit save — no LCD / menu frame / config chart to require;
 *  2. its ProductMapping — a CityBox mirror, read-only in mark1;
 *  3. ops-job "implement new mapping" — an APK push it cannot receive.
 */
class CityboxChillerGuardsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        $this->app->instance(ChillerGateway::class, new FakeChillerGateway);
        foreach (['read product-mappings', 'update operations', 'admin-access operations'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['read product-mappings', 'update operations', 'admin-access operations']);
        $this->actingAs($this->user);
    }

    private function mirror(): ProductMapping
    {
        return ProductMapping::create([
            'name' => 'CityBox E1 (mirror)', 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'is_smart' => false, 'is_active' => true, 'operator_id' => 1,
        ]);
    }

    private function chiller(array $attrs = []): Vend
    {
        return Vend::create(array_merge([
            'code' => 10002, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E1',
            'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1, 'vend_prefix_id' => 1,
            'lcd_monitor_id' => null, 'menu_frame_id' => null, 'vend_config_id' => null,
        ], $attrs));
    }

    // ── 1. Setting/Edit validation ─────────────────────────────────────────

    public function test_chiller_saves_without_vending_hardware_fields(): void
    {
        $vend = $this->chiller(['product_mapping_id' => $this->mirror()->id]);

        $this->post('/vends/'.$vend->id.'/update', [
            'machine_type' => 'smart_chiller',
            'citybox_equipment_id' => 'E1',
            'operator_id' => 1,
            'vend_model_id' => 1,
            'vend_prefix_id' => $vend->vend_prefix_id,
            'product_mapping_id' => $vend->product_mapping_id,
            'lcd_monitor_id' => null,
            'menu_frame_id' => null,
        ])->assertSessionHasNoErrors();

        $fresh = $vend->fresh();
        $this->assertSame('E1', $fresh->citybox_equipment_id);
        $this->assertNull($fresh->lcd_monitor_id);
    }

    public function test_chiller_saves_before_its_first_sync_gave_it_a_mapping_or_prefix(): void
    {
        $vend = $this->chiller(['product_mapping_id' => null, 'vend_prefix_id' => null]);

        $this->post('/vends/'.$vend->id.'/update', [
            'machine_type' => 'smart_chiller', 'citybox_equipment_id' => 'E1',
            'operator_id' => 1, 'vend_model_id' => 1,
            'product_mapping_id' => null, 'vend_prefix_id' => null,
        ])->assertSessionHasNoErrors();
    }

    public function test_vending_machine_still_requires_its_hardware_fields(): void
    {
        $vend = Vend::create(['code' => 9001, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1, 'operator_id' => 1]);

        $this->post('/vends/'.$vend->id.'/update', [
            'operator_id' => 1, 'vend_model_id' => 1, 'lcd_monitor_id' => null, 'menu_frame_id' => null,
        ])->assertSessionHasErrors(['lcd_monitor_id', 'menu_frame_id', 'product_mapping_id', 'vend_prefix_id']);
    }

    public function test_setting_edit_page_still_serves_every_option_list_for_a_chiller(): void
    {
        // Prod vend 1362 still carries config chart 94 ("N/A") and lcd/menu 99 from a
        // hand patch. Edit.vue resolves those stored ids against the option lists and
        // reads `.version` off the config match, so the lists must not be emptied for
        // a chiller — an empty vendConfigOptions crashed the page and nulled hidden
        // columns on the next save.
        $config = \App\Models\VendConfig::create(['name' => 'N/A']);
        $vend = $this->chiller(['product_mapping_id' => $this->mirror()->id, 'vend_config_id' => $config->id]);
        foreach (['read machine-settings', 'update machine-settings'] as $p) {
            $this->user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }

        $this->get('/settings/vend/'.$vend->id.'/update')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Setting/Edit')
                ->where('chillerStatus.equipment_id', 'E1')
                ->has('vendConfigOptions.data', 1)
                ->where('vendConfigOptions.data.0.id', $config->id)
            );
    }

    // ── 2. Mirror mapping is read-only ─────────────────────────────────────

    public function test_mirror_mapping_refuses_every_human_write(): void
    {
        $mirror = $this->mirror();
        $product = Product::create(['code' => 'CB-1', 'name' => 'Peach']);
        $item = ProductMappingItem::create(['product_mapping_id' => $mirror->id, 'channel_code' => '11', 'product_id' => $product->id]);

        $this->post('/product-mappings/'.$mirror->id.'/items/create', ['channel_code' => '12', 'product_id' => $product->id])
            ->assertSessionHasErrors('channel_code');
        $this->post('/product-mappings/items/'.$item->id.'/update', ['product_id' => $product->id])
            ->assertSessionHasErrors('channel_code');
        $this->post('/product-mappings/'.$mirror->id.'/update', ['name' => 'Renamed'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, ProductMappingItem::where('product_mapping_id', $mirror->id)->count());
        $this->assertSame('CityBox E1 (mirror)', $mirror->fresh()->name);
        $this->assertTrue($mirror->isCityboxMirror());
    }

    public function test_ordinary_mapping_is_unaffected_by_the_mirror_guard(): void
    {
        $mapping = ProductMapping::create(['name' => 'VM Menu A', 'is_active' => true, 'operator_id' => 1]);
        $product = Product::create(['code' => 'VM-1', 'name' => 'Cola']);

        $this->post('/product-mappings/'.$mapping->id.'/items/create', ['channel_code' => '11', 'product_id' => $product->id])
            ->assertSessionHasNoErrors();

        $this->assertFalse($mapping->isCityboxMirror());
        $this->assertSame(1, ProductMappingItem::where('product_mapping_id', $mapping->id)->count());
    }

    // ── 3. Ops job: no "implement new mapping" on a chiller ────────────────

    public function test_item_level_implement_new_mapping_is_refused_for_a_chiller(): void
    {
        $customer = Customer::create(['name' => 'Bosch 30F', 'code' => 10001, 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        $vend = $this->chiller(['customer_id' => $customer->id, 'product_mapping_id' => $this->mirror()->id]);
        $job = OpsJob::create(['code' => 900100, 'date' => now()->toDateString(), 'status' => 1, 'delivered_by' => $this->user->id, 'operator_id' => 1]);
        $item = OpsJobItem::create(['ops_job_id' => $job->id, 'vend_id' => $vend->id, 'customer_id' => $customer->id, 'status' => OpsJob::STATUS_PENDING]);

        $this->post('/ops-jobs/items/'.$item->id.'/update/stock-action', ['stock_action_type' => 'implement_new_mapping'])
            ->assertSessionHasErrors('stock_action_type');

        $this->assertNull($item->fresh()->stock_action_type);
    }

    public function test_melted_stock_is_refused_for_a_chiller_and_hidden_from_its_menu(): void
    {
        $customer = Customer::create(['name' => 'Bosch 30F', 'code' => 10001, 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        $vend = $this->chiller(['customer_id' => $customer->id, 'product_mapping_id' => $this->mirror()->id]);
        $job = OpsJob::create(['code' => 900102, 'date' => now()->toDateString(), 'status' => 1, 'delivered_by' => $this->user->id, 'operator_id' => 1]);
        $item = OpsJobItem::create(['ops_job_id' => $job->id, 'vend_id' => $vend->id, 'customer_id' => $customer->id, 'status' => OpsJob::STATUS_PENDING]);

        $this->post('/ops-jobs/items/'.$item->id.'/update/stock-action', ['stock_action_type' => 'melted_stock'])
            ->assertSessionHasErrors('stock_action_type');
        $this->assertNull($item->fresh()->stock_action_type);

        // The counts-based actions still work on a chiller.
        $this->post('/ops-jobs/items/'.$item->id.'/update/stock-action', ['stock_action_type' => 'onsite_adjustment'])
            ->assertSessionHasNoErrors();
        $this->assertSame('onsite_adjustment', $item->fresh()->stock_action_type);

        // The rule the menu reads, flat on the item resource.
        $this->assertSame(['implement_new_mapping', 'melted_stock'], $vend->disallowedStockActions());
        $this->assertSame([], Vend::create(['code' => 9002, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1, 'operator_id' => 1])->disallowedStockActions());
    }

    public function test_job_level_melted_stock_leaves_the_chiller_item_without_an_action(): void
    {
        $customer = Customer::create(['name' => 'Bosch 30F', 'code' => 10001, 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        $vend = $this->chiller(['customer_id' => $customer->id, 'product_mapping_id' => $this->mirror()->id]);
        $vm = Vend::create(['code' => 9003, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1, 'operator_id' => 1, 'customer_id' => $customer->id]);
        $job = OpsJob::create(['code' => 900103, 'date' => now()->toDateString(), 'status' => 1, 'delivered_by' => $this->user->id, 'operator_id' => 1]);
        $chillerItem = OpsJobItem::create(['ops_job_id' => $job->id, 'vend_id' => $vend->id, 'customer_id' => $customer->id, 'status' => OpsJob::STATUS_PENDING]);
        $vmItem = OpsJobItem::create(['ops_job_id' => $job->id, 'vend_id' => $vm->id, 'customer_id' => $customer->id, 'status' => OpsJob::STATUS_PENDING]);

        $this->post('/ops-jobs/'.$job->id.'/update/stock-action', ['stock_action_type' => 'melted_stock'])
            ->assertSessionHasNoErrors();

        $this->assertNull($chillerItem->fresh()->stock_action_type);
        $this->assertSame('melted_stock', $vmItem->fresh()->stock_action_type);
    }

    public function test_job_level_bulk_action_skips_the_chiller_item_instead_of_pushing_a_frame(): void
    {
        $customer = Customer::create(['name' => 'Bosch 30F', 'code' => 10001, 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        $vend = $this->chiller(['customer_id' => $customer->id, 'product_mapping_id' => $this->mirror()->id]);
        $job = OpsJob::create(['code' => 900101, 'date' => now()->toDateString(), 'status' => 1, 'delivered_by' => $this->user->id, 'operator_id' => 1]);
        $item = OpsJobItem::create(['ops_job_id' => $job->id, 'vend_id' => $vend->id, 'customer_id' => $customer->id, 'status' => OpsJob::STATUS_PENDING]);

        $this->post('/ops-jobs/'.$job->id.'/update/stock-action', ['stock_action_type' => 'implement_new_mapping'])
            ->assertSessionHasNoErrors();

        $fresh = $item->fresh();
        $this->assertSame(0, $fresh->opsJobItemChannels()->where('is_upcoming_product', true)->count());
        // The flag itself must not land on the chiller item: completion keys on it to
        // run the old-stock auto-return and the APK channel-frame push.
        $this->assertNull($fresh->stock_action_type);
        $this->assertSame('implement_new_mapping', $job->fresh()->stock_action_type);
    }
}
