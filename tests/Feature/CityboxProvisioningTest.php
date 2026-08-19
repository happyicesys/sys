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

    // ── provision ──────────────────────────────────────────────────────────

    public function test_provision_with_new_customer_forces_operator_allocates_code_binds_and_logs_binding(): void
    {
        $this->gw->seedDevice('ICB26F9FUPE7', '#1', 'visual-2', online: 1);
        $svc = app(DeviceProvisioningService::class);
        $device = $svc->device('ICB26F9FUPE7');

        $vend = $svc->provision($device, ['new_customer' => ['name' => 'Raffles Place L1']], $this->user);

        $this->assertSame($this->op()->id, $vend->operator_id);
        $this->assertSame('smart_chiller', $vend->machine_type);
        $this->assertSame('ICB26F9FUPE7', $vend->citybox_equipment_id);
        $this->assertSame(10001, (int) $vend->code); // running number under the CB operator
        $this->assertSame(VendPrefix::where('name', 'CB')->value('id'), $vend->vend_prefix_id);
        $this->assertSame(VendModel::where('name', 'CityBox F5 (visual-2)')->value('id'), $vend->vend_model_id);
        $this->assertTrue((bool) $vend->is_online);
        $this->assertSame('#1', $vend->citybox_status_json['name']);
        $customer = $vend->customer;
        $this->assertSame('Raffles Place L1', $customer->name);
        $this->assertSame($this->op()->id, $customer->operator_id);
        $this->assertNotNull($vend->binded_at);
        $this->assertSame(1, CustomerVendBinding::where('vend_id', $vend->id)->where('customer_id', $customer->id)->where('is_binding', true)->count());
    }

    public function test_provision_binds_to_existing_customer_by_id(): void
    {
        $existing = Customer::create(['name' => 'Existing Site', 'code' => 10001, 'operator_id' => $this->op()->id, 'status_id' => Customer::STATUS_ACTIVE]);
        $this->gw->seedDevice('E1', 'Singapore1');
        $svc = app(DeviceProvisioningService::class);

        $vend = $svc->provision($svc->device('E1'), ['customer_id' => $existing->id], $this->user);

        $this->assertSame($existing->id, $vend->customer_id);
        $this->assertSame(1, Customer::count()); // no new customer invented
    }

    public function test_name_match_is_normalised_and_scoped_to_citybox_operator(): void
    {
        Customer::create(['name' => '  singapore8 ', 'code' => 10001, 'operator_id' => $this->op()->id, 'status_id' => Customer::STATUS_ACTIVE]);
        Customer::create(['name' => 'Singapore8', 'code' => 10002, 'operator_id' => 999, 'status_id' => Customer::STATUS_ACTIVE]); // other operator: ignored

        $hit = app(DeviceProvisioningService::class)->matchCustomerByName('Singapore8');

        $this->assertNotNull($hit);
        $this->assertSame(10001, (int) $hit->code);
    }

    public function test_provision_refuses_a_second_link_to_the_same_device(): void
    {
        $this->gw->seedDevice('E1');
        $svc = app(DeviceProvisioningService::class);
        $svc->provision($svc->device('E1'), ['new_customer' => ['name' => 'A']], $this->user);

        $this->expectException(CityboxApiException::class);
        $svc->provision($svc->device('E1'), ['new_customer' => ['name' => 'B']], $this->user);
    }

    public function test_provision_is_atomic_when_customer_creation_fails(): void
    {
        $this->gw->seedDevice('E1');
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

        // Explicitly clearing the serial AND changing type is still allowed (deliberate act)
        $this->post("/vends/{$vend->id}/update", $base + ['machine_type' => 'vending_machine', 'citybox_equipment_id' => null]);
        $this->assertNull($vend->fresh()->citybox_equipment_id);
    }

    // ── HTTP ───────────────────────────────────────────────────────────────

    public function test_store_requires_a_customer_choice_and_unique_equipment(): void
    {
        $this->gw->seedDevice('E1', 'Singapore1');
        app(DeviceProvisioningService::class)->devices(fresh: true);

        $this->post('/citybox/vends', ['equipment_id' => 'E1'])->assertSessionHasErrors('customer_id');

        $this->post('/citybox/vends', ['equipment_id' => 'E1', 'new_customer' => ['name' => 'Site A']])
            ->assertRedirect()->assertSessionHas('success');
        $vend = Vend::withoutGlobalScopes()->where('citybox_equipment_id', 'E1')->firstOrFail();
        $this->assertSame('Site A', $vend->customer->name);

        $this->post('/citybox/vends', ['equipment_id' => 'E1', 'new_customer' => ['name' => 'Site B']])
            ->assertSessionHasErrors('equipment_id');
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
