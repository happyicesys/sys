<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Operator;
use App\Models\User;
use App\Models\Vend;
use App\Support\ProductAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The "Machine" dropdown on Setting/Edit jumps to another machine's settings, so
 * it may only list what the viewer can see: their operator, then their machine
 * access (Vend/Temp's cached list is unfiltered, which is why it is not reused).
 */
class SettingMachineJumpOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(int $operatorId): User
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        $user->givePermissionTo(Permission::findOrCreate('read machine-settings', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('update machine-settings', 'web'));

        return $user;
    }

    private function machine(int $code, int $operatorId, array $attributes = []): Vend
    {
        return Vend::create(['code' => $code, 'is_active' => 1, 'operator_id' => $operatorId] + $attributes);
    }

    /** @return array<int, array{id:int,label:string}> */
    private function jumpOptions(User $user, Vend $vend): array
    {
        $options = [];
        $this->actingAs($user)->get("/settings/vend/{$vend->id}/update")->assertOk()
            ->assertInertia(function ($page) use (&$options) {
                $options = $page->toArray()['props']['machineJumpOptions'];
            });

        return $options;
    }

    public function test_label_carries_the_code_prefix_site_and_ref_id(): void
    {
        $customer = Customer::create(['code' => 'SC1', 'name' => 'Sentosa Cove', 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        $chiller = $this->machine(6001, 1, ['code_prefix' => 'C', 'customer_id' => $customer->id]);
        $unbound = $this->machine(2031, 1);

        $options = collect($this->jumpOptions($this->viewer(1), $chiller))->keyBy('id');

        $this->assertSame('C6001 • Sentosa Cove • #'.($customer->id + Customer::RUNNING_NUMBER_INIT), $options[$chiller->id]['label']);
        $this->assertSame('2031', $options[$unbound->id]['label']);
    }

    public function test_another_operator_lists_only_its_own_machines(): void
    {
        // HIPL first: the first operator in an empty table gets id 1, which sees everything.
        Operator::firstOrCreate(['code' => 'HIPL'], ['name' => 'HIPL']);
        $other = Operator::firstOrCreate(['code' => 'XO'], ['name' => 'XO']);
        $own = $this->machine(3001, $other->id);
        $this->machine(3002, 1);

        $this->assertSame([$own->id], collect($this->jumpOptions($this->viewer($other->id), $own))->pluck('id')->all());
    }

    public function test_a_machine_switched_to_no_show_is_left_out(): void
    {
        $shown = $this->machine(4001, 1);
        $hidden = $this->machine(4002, 1);

        $viewer = $this->viewer(1);
        $viewer->forceFill(['product_access_mode' => ProductAccess::MODE_LIST])->save();
        $viewer->hiddenVends()->attach($hidden->id);

        $ids = collect($this->jumpOptions($viewer, $shown))->pluck('id');

        $this->assertTrue($ids->contains($shown->id));
        $this->assertFalse($ids->contains($hidden->id));
    }
}
