<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Exceptions\CityboxApiException;
use App\Models\Customer;
use App\Models\CustomerVendBinding;
use App\Models\Operator;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendModel;
use App\Models\VendPrefix;
use App\Services\Citybox\DeviceProvisioningService;
use Database\Seeders\CityboxOperatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

class CityboxProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
        Operator::create(['code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1]);
        (new CityboxOperatorSeeder)->run();
        Permission::findOrCreate('create machine-settings', 'web');
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('create machine-settings');
        $this->actingAs($this->user);
    }

    private function op(): Operator
    {
        return Operator::where('code', 'CB')->firstOrFail();
    }

    // ── seed ───────────────────────────────────────────────────────────────

    public function test_seeder_is_idempotent_and_creates_operator_prefix_models(): void
    {
        (new CityboxOperatorSeeder)->run(); // second run
        $this->assertSame(1, Operator::where('code', 'CB')->count());
        $this->assertSame(1, VendPrefix::where('name', 'CB')->count());
        $this->assertSame(3, VendModel::where('name', 'like', 'CityBox%')->count());
    }

    // ── device listing ─────────────────────────────────────────────────────

    public function test_devices_excludes_already_linked_and_reports_them(): void
    {
        $this->gw->seedDevice('E1', 'Singapore1')->seedDevice('E2', 'Singapore2');
        Vend::create(['code' => 5, 'machine_type' => 'smart_chiller', 'citybox_equipment_id' => 'E1', 'is_active' => 1]);

        $r = app(DeviceProvisioningService::class)->devices(fresh: true);

        $this->assertSame(['E2'], $r['unlinked']->map(fn ($d) => $d->equipmentId)->all());
        $this->assertArrayHasKey('E1', $r['linked']);
    }

    public function test_devices_carry_the_machine_id_each_one_would_import_as(): void
    {
        // The picker names the C-code ops know the fleet by, and says which devices
        // cannot be imported, before anything is selected (Brian, 2026-09-22).
        $this->gw->seedDevice('E1', 'C6003')->seedDevice('E2', 'Singapore8')->seedDevice('E3', 'C6004 HI Office');
        $held = Vend::withoutGlobalScopes()->create(['code' => 6004, 'code_prefix' => 'C', 'is_active' => 1]);

        $ids = app(DeviceProvisioningService::class)->devices(fresh: true)['machine_ids'];

        $this->assertSame(['label' => 'C6003', 'error' => null], $ids['E1']);
        $this->assertNull($ids['E2']['label']);
        $this->assertStringContainsString('no machine ID', $ids['E2']['error']);
        $this->assertSame('C6004', $ids['E3']['label']);
        $this->assertStringContainsString("used by vend #{$held->id}", $ids['E3']['error']);

        // …and on the wire, per row, the same way the preview card gets it.
        $this->getJson('/citybox/devices')->assertOk()
            ->assertJsonPath('unlinked.0.machine_id', 'C6003')
            ->assertJsonPath('unlinked.0.machine_id_error', null)
            ->assertJsonPath('unlinked.2.machine_id', null);
    }

    // ── provision ──────────────────────────────────────────────────────────

    public function test_provision_imports_the_machine_with_no_site(): void
    {
        // "Site — Primary: Sys" (Brian, 2026-09-19): a machine arrives with no site. Until then
        // importing REQUIRED one and offered to create it from the device name — the fleet's
        // "Singapore5" sites.
        $this->gw->seedDevice('ICB26F9FUPE7', 'C6002', 'visual-2', online: 1);
        $svc = app(DeviceProvisioningService::class);
        $device = $svc->device('ICB26F9FUPE7');

        $vend = $svc->provision($device, [], $this->user);

        $this->assertSame($this->op()->id, $vend->operator_id);
        $this->assertSame('smart_chiller', $vend->machine_type);
        $this->assertSame('ICB26F9FUPE7', $vend->citybox_equipment_id);
        $this->assertSame(6002, (int) $vend->code); // OPS Pro's machine ID, not a CB running number
        $this->assertSame('C', $vend->code_prefix);
        $this->assertSame('C6002', $vend->codeLabel());
        $this->assertSame(VendPrefix::where('name', 'CB')->value('id'), $vend->vend_prefix_id);
        $this->assertSame(VendModel::where('name', 'CityBox F5 (visual-2)')->value('id'), $vend->vend_model_id);
        $this->assertTrue((bool) $vend->is_online);
        $this->assertSame('C6002', $vend->citybox_status_json['name']);
        $this->assertNull($vend->customer_id);
        $this->assertNull($vend->binded_at);
        $this->assertSame(0, Customer::count(), 'no site is ever invented from a device');
        $this->assertSame(0, CustomerVendBinding::where('vend_id', $vend->id)->count());
    }

    public function test_provision_may_share_the_number_with_an_old_unprefixed_vend(): void
    {
        // Prod 2026-09-19: 5001–5004, 6001 and 6002 are inactive vending machines. The
        // chiller still takes OPS Pro's C6001 — only the (prefix, code) pair must be free.
        $otherOperator = Operator::create(['code' => 'OTHR', 'name' => 'Other', 'country_id' => 1]);
        Vend::withoutGlobalScopes()->create(['code' => 6001, 'operator_id' => $otherOperator->id]);
        $this->gw->seedDevice('E1', 'C6001');
        $svc = app(DeviceProvisioningService::class);

        $vend = $svc->provision($svc->device('E1'), [], $this->user);

        $this->assertSame('C6001', $vend->codeLabel());
        $this->assertSame(2, Vend::withoutGlobalScopes()->where('code', 6001)->count());
    }

    public function test_provision_refuses_a_machine_id_another_vend_holds(): void
    {
        $this->gw->seedDevice('E1', 'C6001')->seedDevice('E2', 'C6001 HI Office');
        $svc = app(DeviceProvisioningService::class);
        $svc->provision($svc->device('E1'), [], $this->user);

        try {
            $svc->provision($svc->device('E2'), [], $this->user);
            $this->fail('expected a duplicate machine ID to be refused');
        } catch (CityboxApiException $e) {
            $this->assertStringContainsString('C6001 is already used', $e->getMessage());
        }
        $this->assertSame(0, Vend::withoutGlobalScopes()->where('citybox_equipment_id', 'E2')->count());
    }

    public function test_provision_refuses_a_name_that_carries_no_machine_id(): void
    {
        // Their old placeholder names ("Singapore8", "#1") never become an invented code.
        $this->gw->seedDevice('E1', 'Singapore8');
        $svc = app(DeviceProvisioningService::class);

        $this->assertNull($svc->preview('E1')['machine_id']);
        $this->assertStringContainsString('has no machine ID', $svc->preview('E1')['machine_id_error']);

        $this->expectException(CityboxApiException::class);
        $svc->provision($svc->device('E1'), [], $this->user);
    }

    public function test_provision_binds_to_existing_customer_by_id(): void
    {
        $existing = Customer::create(['name' => 'Existing Site', 'code' => 10001, 'operator_id' => $this->op()->id, 'status_id' => Customer::STATUS_ACTIVE]);
        $this->gw->seedDevice('E1', 'C5001');
        $svc = app(DeviceProvisioningService::class);

        $vend = $svc->provision($svc->device('E1'), ['customer_id' => $existing->id], $this->user);

        $this->assertSame($existing->id, $vend->customer_id);
        $this->assertNotNull($vend->binded_at);
        $this->assertSame(1, CustomerVendBinding::where('vend_id', $vend->id)->where('customer_id', $existing->id)->where('is_binding', true)->count());
        $this->assertSame(1, Customer::count()); // no new customer invented
    }

    public function test_provision_refuses_a_second_link_to_the_same_device(): void
    {
        $this->gw->seedDevice('E1', 'C6001');
        $svc = app(DeviceProvisioningService::class);
        $svc->provision($svc->device('E1'), [], $this->user);

        $this->expectException(CityboxApiException::class);
        $svc->provision($svc->device('E1'), [], $this->user);
    }

    public function test_provision_is_atomic_when_customer_creation_fails(): void
    {
        $this->gw->seedDevice('E1', 'C6001');
        $svc = app(DeviceProvisioningService::class);
        // customer_id that does not exist → findOrFail throws inside the transaction
        try {
            $svc->provision($svc->device('E1'), ['customer_id' => 424242], $this->user);
            $this->fail('expected throw');
        } catch (\Throwable) {
        }
        $this->assertSame(0, Vend::withoutGlobalScopes()->where('citybox_equipment_id', 'E1')->count()); // rolled back
    }

    public function test_unique_index_on_citybox_equipment_id_is_the_real_duplicate_guard(): void
    {
        Vend::create(['code' => 9701, 'machine_type' => 'smart_chiller', 'citybox_equipment_id' => 'DUP1', 'is_active' => 1]);
        $this->expectException(\Illuminate\Database\QueryException::class);
        Vend::create(['code' => 9702, 'machine_type' => 'smart_chiller', 'citybox_equipment_id' => 'DUP1', 'is_active' => 1]);
    }

    public function test_settings_save_cannot_silently_demote_a_linked_chiller_and_wipe_its_serial(): void
    {
        // Regression (prod 2026-08-19, vend 1360): a Save with an unmatched Machine Type
        // picker submitted machine_type=vending_machine, and the server dutifully nulled
        // the CityBox serial. Now the server refuses the demotion while a serial exists.
        \Spatie\Permission\Models\Permission::findOrCreate('update machine-settings', 'web');
        $this->user->givePermissionTo('update machine-settings');
        $vend = Vend::create(['code' => 10001, 'machine_type' => 'smart_chiller', 'citybox_equipment_id' => 'ICB26F9605R9', 'is_active' => 1, 'operator_id' => $this->op()->id]);
        $base = ['name' => null, 'begin_date' => '2026-08-19', 'lcd_monitor_id' => 1, 'menu_frame_id' => 1, 'operator_id' => $this->op()->id,
            'vend_model_id' => 1, 'vend_prefix_id' => 1, 'product_mapping_id' => 1, 'vend_config_id' => null, 'status' => 'active', 'is_fan_enabled' => true];

        // The bad payload the old page could send
        $this->from('/settings/vend/'.$vend->id.'/update')
            ->post("/vends/{$vend->id}/update", $base + ['machine_type' => 'vending_machine', 'citybox_equipment_id' => 'ICB26F9605R9'])
            ->assertSessionHasErrors('machine_type');
        $this->assertSame('ICB26F9605R9', $vend->fresh()->citybox_equipment_id); // untouched
        $this->assertSame('smart_chiller', $vend->fresh()->machine_type);

        // Staying a Smart Chiller with an empty / missing serial never wipes the link
        // (prod 2026-08-20: the Settings page could not see the value, so every Save
        // posted null). Editable only while empty; no unbind for chillers.
        $this->post("/vends/{$vend->id}/update", $base + ['machine_type' => 'smart_chiller', 'citybox_equipment_id' => null]);
        $this->assertSame('ICB26F9605R9', $vend->fresh()->citybox_equipment_id);
        $this->post("/vends/{$vend->id}/update", $base + ['machine_type' => 'smart_chiller', 'citybox_equipment_id' => '']);
        $this->assertSame('ICB26F9605R9', $vend->fresh()->citybox_equipment_id);
        $this->post("/vends/{$vend->id}/update", $base + ['machine_type' => 'smart_chiller']);
        $this->assertSame('ICB26F9605R9', $vend->fresh()->citybox_equipment_id);

        // Explicitly clearing the serial AND changing type is still allowed (deliberate act)
        $this->post("/vends/{$vend->id}/update", $base + ['machine_type' => 'vending_machine', 'citybox_equipment_id' => null]);
        $this->assertNull($vend->fresh()->citybox_equipment_id);
    }

    public function test_settings_page_receives_the_citybox_link_fields(): void
    {
        // Regression (prod 2026-08-20, vend 1361): SettingController::edit selected an
        // explicit column list without citybox_equipment_id → the page showed an empty
        // field for a linked chiller (and a Save then wiped it).
        foreach (['read machine-settings', 'update machine-settings'] as $perm) {
            \Spatie\Permission\Models\Permission::findOrCreate($perm, 'web');
        }
        $this->user->givePermissionTo(['read machine-settings', 'update machine-settings']);
        $vend = Vend::create(['code' => 10002, 'machine_type' => 'smart_chiller', 'citybox_equipment_id' => 'ICB26F9FEAGC', 'is_active' => 1, 'operator_id' => $this->op()->id,
            'is_online' => 1, 'citybox_synced_at' => now(), 'citybox_status_json' => ['name' => 'Singapore2', 'device_type' => 'visual-2']]);

        $this->get('/settings/vend/'.$vend->id.'/update')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Setting/Edit')
                ->where('vend.citybox_equipment_id', 'ICB26F9FEAGC')
                ->where('vend.citybox_status_json.name', 'Singapore2')
                ->has('vend.citybox_synced_at'));
    }

    // ── HTTP ───────────────────────────────────────────────────────────────

    public function test_store_imports_without_a_site_and_refuses_to_make_one_from_the_device(): void
    {
        $this->gw->seedDevice('E1', 'C5001')->seedDevice('E2', 'C5002');
        app(DeviceProvisioningService::class)->devices(fresh: true);

        // An old client still posting a site-from-device is refused, not obeyed.
        $this->post('/citybox/vends', ['equipment_id' => 'E1', 'new_customer' => ['name' => 'Singapore1']])
            ->assertSessionHasErrors('new_customer');
        $this->assertSame(0, Vend::withoutGlobalScopes()->where('citybox_equipment_id', 'E1')->count());

        $this->post('/citybox/vends', ['equipment_id' => 'E1'])
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'with no site'));
        $vend = Vend::withoutGlobalScopes()->where('citybox_equipment_id', 'E1')->firstOrFail();
        $this->assertNull($vend->customer_id);
        $this->assertSame(0, Customer::count());

        // The shortcut: bind to a site that already exists.
        $site = Customer::create(['name' => 'Bosch 30F', 'code' => 10001, 'operator_id' => $this->op()->id, 'status_id' => Customer::STATUS_ACTIVE]);
        $this->post('/citybox/vends', ['equipment_id' => 'E2', 'customer_id' => $site->id])
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'Bosch 30F'));

        $this->post('/citybox/vends', ['equipment_id' => 'E1'])->assertSessionHasErrors('equipment_id');
    }

    public function test_an_imported_chiller_is_bound_to_a_site_from_machine_settings(): void
    {
        // The second half of "Site — Primary: Sys": the site is made in mark1 like any
        // other, then bound with the same Site picker every unbound machine uses.
        foreach (['update vend-settings', 'update customers', 'update machine-settings'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $this->user->givePermissionTo(['update vend-settings', 'update customers', 'update machine-settings']);
        $this->gw->seedDevice('E1', 'C5001');
        $svc = app(DeviceProvisioningService::class);
        $vend = $svc->provision($svc->device('E1'), [], $this->user);
        $site = Customer::create(['name' => 'Bosch 30F', 'code' => 10001, 'operator_id' => $this->op()->id, 'status_id' => Customer::STATUS_ACTIVE]);

        // Setting/Edit posts the unbound machine's id plus the picked site; no site is loaded yet.
        $this->post('/customers/0/update', ['id' => $vend->id, 'is_existing' => 1, 'customer_id' => $site->id, 'customer' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame($site->id, $vend->fresh()->customer_id);
        $this->assertSame(1, CustomerVendBinding::where('vend_id', $vend->id)->where('customer_id', $site->id)->where('is_binding', true)->count());
    }

    public function test_devices_endpoint_reports_disabled_cleanly(): void
    {
        config(['citybox.openapi.enabled' => false]);
        $this->getJson('/citybox/devices')->assertOk()->assertJson(['enabled' => false, 'unlinked' => []]);
    }

    public function test_devices_endpoint_needs_permission(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/citybox/devices')->assertForbidden();
    }
}
