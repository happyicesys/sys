<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Customer;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\Operator;
use App\Models\User;
use App\Services\Grab\GrabStoreChangeRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * "Update on Grab" (Brian, 2026-11-11): Grab takes no new store-change request by email, so ConnectVend prepares it
 * for the GrabMerchant app or Grab's Help Centre form and keeps track of it in the store's history — HIPL stores only,
 * since the other operators change their stores themselves in the app.
 */
class GrabStoreChangeRequestTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const STORE = '4-C6VGNELVVNAZN6';

    private User $user;

    private DeliveryPlatformRefNumber $ref;

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        Queue::fake();
        Http::fake();
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);
        $binding = $this->bindGrabStore(2338, self::STORE, [11 => ['qty' => 5, 'capacity' => 10]]);

        // The machine's Site, with the delivery address Grab should show.
        $customer = Customer::forceCreate(['name' => 'Tampines SAFRA (Indoor)', 'code' => '15895', 'operator_id' => $this->grabMapping->operator_id]);
        Address::forceCreate([
            'modelable_type' => Customer::class, 'modelable_id' => $customer->id, 'type' => 2, 'country_id' => 1,
            'block_num' => '1A', 'street_name' => 'Tampines St 92', 'unit_num' => '01-01', 'building' => 'Tampines SAFRA', 'postcode' => '528882',
        ]);
        $binding->forceFill(['customer_id' => $customer->id])->save();
        $this->ref = $binding->deliveryPlatformRefNumber;

        foreach (['read delivery-platform-vends', 'update delivery-platform-vends'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->user = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $this->user->givePermissionTo(['read delivery-platform-vends', 'update delivery-platform-vends']);
    }

    public function test_the_window_starts_from_the_machines_site_address_and_a_go_live_date(): void
    {
        $this->actingAs($this->user)->getJson("/grab-stores/{$this->ref->id}/change-request")
            ->assertOk()
            ->assertJsonPath('merchant_id', self::STORE)
            ->assertJsonPath('vend_code', 2338)
            ->assertJsonPath('address', 'Blk 1A Tampines St 92, #01-01, Tampines SAFRA')
            ->assertJsonPath('postal_code', '528882')
            ->assertJsonPath('go_live_on', now()->addDays(GrabStoreChangeRequests::LEAD_DAYS)->toDateString())
            ->assertJsonPath('help_centre_url', GrabStoreChangeRequests::HELP_CENTRE_URL)
            ->assertJsonPath('open', null);
    }

    public function test_a_sent_request_is_kept_on_the_store_shown_on_its_row_until_marked_done(): void
    {
        $this->actingAs($this->user)->post("/grab-stores/{$this->ref->id}/change-request", [
            'kind' => 'address', 'route' => 'app', 'address' => 'Blk 1A Tampines St 92, Level 1 lobby', 'postal_code' => '528882', 'go_live_on' => '2026-11-14',
        ])->assertSessionHasNoErrors();

        $event = DeliveryPlatformStoreEvent::query()->where('event', DeliveryPlatformStoreEvent::EVENT_GRAB_CHANGE_REQUESTED)->sole();
        // MySQL keeps JSON keys in its own order: compare the keys and values, not their order.
        $this->assertEquals(['kind' => 'address', 'route' => 'app', 'address' => 'Blk 1A Tampines St 92, Level 1 lobby', 'postal_code' => '528882', 'go_live_on' => '2026-11-14', 'details' => null, 'vend_code' => 2338], $event->data);
        $this->assertSame($this->user->id, $event->user_id);

        $this->actingAs($this->user)->get('/grab-stores')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stores.data.0.can_ask_grab', true)
            ->where('stores.data.0.grab_change.kind', 'address')
            ->where('stores.data.0.grab_change.go_live_on', '2026-11-14')
            ->where('stores.data.0.grab_change.requested_by', $this->user->name));

        $this->actingAs($this->user)->post("/grab-stores/{$this->ref->id}/change-request/done")->assertSessionHasNoErrors();
        $this->assertSame([], app(GrabStoreChangeRequests::class)->open(collect([$this->ref->id])));
        $this->actingAs($this->user)->get('/grab-stores')->assertInertia(fn (AssertableInertia $page) => $page->where('stores.data.0.grab_change', null));
    }

    public function test_a_change_other_than_the_address_needs_the_details_and_an_address_change_needs_the_address(): void
    {
        $this->actingAs($this->user)->post("/grab-stores/{$this->ref->id}/change-request", ['kind' => 'name', 'route' => 'form'])
            ->assertSessionHasErrors('details');
        $this->actingAs($this->user)->post("/grab-stores/{$this->ref->id}/change-request", ['kind' => 'address', 'route' => 'form', 'address' => ''])
            ->assertSessionHasErrors('address');
        $this->assertSame(0, DeliveryPlatformStoreEvent::query()->where('event', DeliveryPlatformStoreEvent::EVENT_GRAB_CHANGE_REQUESTED)->count());
    }

    public function test_only_hipl_stores_and_only_people_who_may_change_stores(): void
    {
        // Another operator's store: they change it themselves in the GrabMerchant app.
        $other = Operator::forceCreate(['name' => 'Ariska', 'code' => 'ARK', 'country_id' => 1, 'created_by' => 1, 'gst_vat_rate' => 9]);
        $this->ref->update(['operator_id' => $other->id]);
        $admin = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $admin->givePermissionTo(['read delivery-platform-vends', 'update delivery-platform-vends']);
        $this->actingAs($admin)->getJson("/grab-stores/{$this->ref->id}/change-request")->assertNotFound();
        $this->actingAs($admin)->post("/grab-stores/{$this->ref->id}/change-request", ['kind' => 'name', 'route' => 'app', 'details' => 'x'])->assertNotFound();

        $this->ref->update(['operator_id' => $this->grabMapping->operator_id]);
        $reader = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $reader->givePermissionTo('read delivery-platform-vends');
        $this->actingAs($reader)->getJson("/grab-stores/{$this->ref->id}/change-request")->assertForbidden();
        $this->actingAs($reader)->post("/grab-stores/{$this->ref->id}/change-request", ['kind' => 'name', 'route' => 'app', 'details' => 'x'])->assertForbidden();
    }
}
