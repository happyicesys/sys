<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Operator;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Settings → Create machine (POST /settings/vend/store), the Vending Machine / Smart
 * Freezer path. A Smart Chiller is created through POST /citybox/vends instead
 * (CityboxProvisioningTest).
 */
class MachineCreateTest extends TestCase
{
    use RefreshDatabase;

    private Operator $mine;

    private Operator $other;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        // Operator 1 is unrestricted (sees every vend); the viewer must be a scoped operator.
        Operator::create(['code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1]);
        $this->mine = Operator::create(['code' => 'MINE', 'name' => 'Mine', 'country_id' => 1]);
        $this->other = Operator::create(['code' => 'OTHR', 'name' => 'Other', 'country_id' => 1]);

        foreach (['read machine-settings', 'create machine-settings'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $this->user = User::factory()->create(['operator_id' => $this->mine->id]);
        $this->user->givePermissionTo(['read machine-settings', 'create machine-settings']);
        $this->actingAs($this->user);
    }

    public function test_creates_a_vending_machine_under_the_creators_operator(): void
    {
        $this->post('/settings/vend/store', ['code' => 70001, 'machine_type' => 'vending_machine', 'begin_date' => '2026-09-15'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $vend = Vend::withoutGlobalScopes()->where('code', 70001)->sole();
        $this->assertSame('vending_machine', $vend->machine_type);
        $this->assertSame($this->mine->id, (int) $vend->operator_id);
        $this->assertSame('2026-09-15', $vend->begin_date->toDateString());
    }

    public function test_missing_or_null_machine_type_defaults_to_vending_machine(): void
    {
        $this->post('/settings/vend/store', ['code' => 70002, 'machine_type' => null])->assertSessionHasNoErrors();

        $this->assertSame('vending_machine', Vend::withoutGlobalScopes()->where('code', 70002)->value('machine_type'));
    }

    public function test_refuses_a_smart_chiller_which_must_come_from_a_citybox_device(): void
    {
        $this->post('/settings/vend/store', ['code' => 70003, 'machine_type' => 'smart_chiller'])
            ->assertSessionHasErrors('machine_type');

        $this->assertFalse(Vend::withoutGlobalScopes()->where('code', 70003)->exists());
    }

    public function test_refuses_a_machine_id_another_operator_already_holds(): void
    {
        // Invisible to this viewer through the operator scope — the old check missed it and
        // created a second vend with the same code (prod: code 10002, vends 909 + 1363).
        Vend::withoutGlobalScopes()->create(['code' => 70004, 'operator_id' => $this->other->id]);

        $this->post('/settings/vend/store', ['code' => 70004])
            ->assertSessionHasErrors(['code' => 'Machine ID 70004 is already used by another operator.']);

        $this->assertSame(1, Vend::withoutGlobalScopes()->where('code', 70004)->count());
    }

    public function test_refuses_a_machine_id_the_viewer_already_holds(): void
    {
        Vend::withoutGlobalScopes()->create(['code' => 70005, 'operator_id' => $this->mine->id]);

        $this->post('/settings/vend/store', ['code' => 70005])
            ->assertSessionHasErrors(['code' => 'Machine ID 70005 already exists.']);
    }

    public function test_ignores_every_field_the_create_form_does_not_own(): void
    {
        // The page used to post its whole form, so a site picked in the CityBox branch rode
        // along and the new vend was bound with no binding history.
        $site = Customer::create(['name' => 'Stray Site', 'operator_id' => $this->mine->id, 'status_id' => Customer::STATUS_ACTIVE]);

        $this->post('/settings/vend/store', [
            'code' => 70006,
            'machine_type' => 'vending_machine',
            'customer_id' => $site->id,
            'operator_id' => $this->other->id,
            'citybox_equipment_id' => 'ICB-STRAY',
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $vend = Vend::withoutGlobalScopes()->where('code', 70006)->sole();
        $this->assertNull($vend->customer_id);
        $this->assertNull($vend->citybox_equipment_id);
        $this->assertSame($this->mine->id, (int) $vend->operator_id);
    }

    public function test_rejects_a_non_numeric_machine_id(): void
    {
        $this->post('/settings/vend/store', ['code' => 'abc'])->assertSessionHasErrors('code');
    }
}
