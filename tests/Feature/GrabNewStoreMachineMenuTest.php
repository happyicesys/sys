<?php

namespace Tests\Feature;

use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\DeliveryProductMappingVend;
use App\Models\User;
use App\Models\Vend;
use App\Services\GrabStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * New stores skip the old Grab menus (Brian, 2026-11-11: "don't use the old menu … skip it for the new store"): once
 * an operator sells on the machine menu, a store it adds lists what is in its machine at its price rule from the
 * start, and nobody picks a template — the link gets the operator's usual one, which Grab never sees.
 */
class GrabNewStoreMachineMenuTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        Queue::fake();
        Http::fake();
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);

        foreach (['read delivery-platform-vends', 'update delivery-platform-vends'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->user = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $this->user->givePermissionTo(['read delivery-platform-vends', 'update delivery-platform-vends']);
    }

    private function machine(int $code): Vend
    {
        return Vend::query()->withoutGlobalScopes()->where('code', $code)->first()
            ?? Vend::forceCreate(['code' => $code, 'operator_id' => $this->grabMapping->operator_id]);
    }

    private function switchOneStore(): void
    {
        $binding = $this->bindGrabStore(2502, '4-C6BUGNN3NEUAKE', [11 => ['qty' => 5, 'capacity' => 10]]);
        app(GrabStoreService::class)->setMenuSource($binding->deliveryPlatformRefNumber, true, null);
    }

    public function test_an_operator_on_the_machine_menu_adds_a_store_that_lists_its_machine_with_no_template_picked(): void
    {
        $this->switchOneStore();
        $this->machine(4407);

        $this->actingAs($this->user)->post('/grab-stores', [
            'operator_id' => $this->grabMapping->operator_id, 'ref_number' => '4-c4mjka6frul1rx', 'vend_code' => '4407', 'skip_grab_check' => true,
        ])->assertSessionHasNoErrors();

        $ref = DeliveryPlatformRefNumber::query()->where('ref_number', '4-C4MJKA6FRUL1RX')->sole();
        $this->assertTrue($ref->usesMachineMenu(), 'on the machine menu from the start');
        $binding = $ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->sole();
        $this->assertSame($this->grabMapping->id, (int) $binding->delivery_product_mapping_id, 'linked to the operator\'s usual template');
        $this->assertEquals(['machine_menu' => true, 'new_store' => true], DeliveryPlatformStoreEvent::query()
            ->where('delivery_platform_ref_number_id', $ref->id)->where('event', DeliveryPlatformStoreEvent::EVENT_MENU_SOURCE)->sole()->data);

        $this->actingAs($this->user)->get('/grab-stores')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('machineMenuOperatorIds', [$this->grabMapping->operator_id]));
    }

    public function test_an_operator_still_on_the_old_menus_must_pick_a_template_as_before(): void
    {
        $this->machine(4407);

        $this->actingAs($this->user)->post('/grab-stores', [
            'operator_id' => $this->grabMapping->operator_id, 'ref_number' => '4-C4MJKA6FRUL1RX', 'vend_code' => '4407', 'skip_grab_check' => true,
        ])->assertSessionHasErrors('delivery_product_mapping_id');
        $this->assertSame(0, DeliveryPlatformRefNumber::query()->where('ref_number', '4-C4MJKA6FRUL1RX')->count(), 'nothing saved');

        $this->actingAs($this->user)->post('/grab-stores', [
            'operator_id' => $this->grabMapping->operator_id, 'ref_number' => '4-C4MJKA6FRUL1RX', 'vend_code' => '4407',
            'delivery_product_mapping_id' => $this->grabMapping->id, 'skip_grab_check' => true,
        ])->assertSessionHasNoErrors();
        $this->assertFalse(DeliveryPlatformRefNumber::query()->where('ref_number', '4-C4MJKA6FRUL1RX')->sole()->usesMachineMenu());
    }

    public function test_putting_a_new_store_on_a_machine_later_needs_no_template_but_a_store_put_back_on_its_old_menu_stays_there(): void
    {
        $this->switchOneStore();
        $service = app(GrabStoreService::class);

        // Added with no machine: on the machine menu already; put on one later with no template.
        $new = $service->addStore($this->grabMapping->operator_id, '4-C7NEWSTORE0001', null, $this->user->id, skipGrabCheck: true);
        $this->actingAs($this->user)->post("/grab-stores/{$new->id}/bind", ['vend_code' => (string) $this->machine(4408)->code])->assertSessionHasNoErrors();
        $this->assertTrue($new->fresh()->usesMachineMenu());
        $this->assertSame(1, DeliveryProductMappingVend::query()->withoutGlobalScopes()->where('delivery_platform_ref_number_id', $new->id)->count());

        // Someone put this one back on its old menu on purpose: a later bind asks for a template again.
        $old = $service->addStore($this->grabMapping->operator_id, '4-C7OLDMENU00001', null, $this->user->id, skipGrabCheck: true);
        $service->setMenuSource($old, false, $this->user->id);
        $this->assertFalse($service->willUseMachineMenu($old->fresh()));
        $this->actingAs($this->user)->post("/grab-stores/{$old->id}/bind", ['vend_code' => (string) $this->machine(4409)->code])
            ->assertSessionHasErrors('delivery_product_mapping_id');
    }
}
