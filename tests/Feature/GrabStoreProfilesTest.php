<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Customer;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\User;
use App\Services\Grab\GrabStoreChangeRequests;
use App\Services\Grab\GrabStoreProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * The daily GrabMerchant website read (Brian, 2026-11-12): Grab's partner API gives no store address, so what
 * customers see on Grab is read from each store's Store profile page and compared with the machine's Site.
 */
class GrabStoreProfilesTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const STORE = '4-C6VGNELVVNAZN6';

    // A Store profile page as the GrabMerchant website shows it (main element's text).
    private const PAGE = "Ice Cream Vending Machine (Unmanned Store) - Tampines SAFRA\nEdit\nDelivery hours\n•\nMon-Sun, 12:00 AM-11:59 PM\nEdit\n1A, Tampines Street 92, #01-01, Tampines, 528882, 528882, Singapore\nEdit\n6587188597\nEdit\ngrabcollection.sg@happyice.com.sg\nEdit\nFood delivery · GrabPay\nOthers\nStore ID\n4-C6VGNELVVNAZN6";

    private User $user;

    private DeliveryPlatformRefNumber $ref;

    private Customer $tampines;

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        Queue::fake();
        Http::fake();
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);
        $binding = $this->bindGrabStore(2338, self::STORE, [11 => ['qty' => 5, 'capacity' => 10]]);

        $this->tampines = $this->site('Tampines SAFRA (Indoor)', '15895', '528882');
        $binding->forceFill(['customer_id' => $this->tampines->id])->save();
        $binding->vend->forceFill(['customer_id' => $this->tampines->id])->save();
        $this->ref = $binding->deliveryPlatformRefNumber;

        foreach (['read delivery-platform-vends', 'update delivery-platform-vends'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->user = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $this->user->givePermissionTo(['read delivery-platform-vends', 'update delivery-platform-vends']);
    }

    private function site(string $name, string $code, string $postcode): Customer
    {
        $customer = Customer::forceCreate(['name' => $name, 'code' => $code, 'operator_id' => $this->grabMapping->operator_id]);
        Address::forceCreate([
            'modelable_type' => Customer::class, 'modelable_id' => $customer->id, 'type' => 2, 'country_id' => 1,
            'full_address' => $name, 'postcode' => $postcode,
        ]);

        return $customer;
    }

    private function send(array $stores): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->postJson('/grab-stores/grab-profiles', ['stores' => $stores]);
    }

    public function test_the_store_profile_page_is_read_line_by_line(): void
    {
        $this->assertSame([
            'merchant_id' => self::STORE,
            'name' => 'Ice Cream Vending Machine (Unmanned Store) - Tampines SAFRA',
            'hours' => 'Mon-Sun, 12:00 AM-11:59 PM',
            'address' => '1A, Tampines Street 92, #01-01, Tampines, 528882, 528882, Singapore',
        ], GrabStoreProfiles::parse(self::PAGE));
        $this->assertSame('159919', GrabStoreProfiles::postalCode('2, Alexandra Road, #01-03A, Bukit Merah, 159919'));
        $this->assertSame('12940', GrabStoreProfiles::postalCode('Jl. Sudirman 1, Jakarta 12940'));
        $this->assertNull(GrabStoreProfiles::postalCode('Singapore'));
    }

    public function test_a_read_is_kept_on_the_store_and_shown_with_a_match_against_the_site(): void
    {
        $this->send([['merchant_id' => self::STORE, 'text' => self::PAGE]])
            ->assertOk()->assertJson(['read' => 1, 'changed' => 1, 'unknown' => [], 'mismatched' => []]);

        $ref = $this->ref->fresh();
        $this->assertSame('528882', $ref->grab_postal_code);
        $this->assertSame('Mon-Sun, 12:00 AM-11:59 PM', $ref->grab_hours);
        $this->assertNotNull($ref->grab_profile_checked_at);

        $this->actingAs($this->user)->get('/grab-stores')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stores.data.0.grab_profile.name', 'Ice Cream Vending Machine (Unmanned Store) - Tampines SAFRA')
            ->where('stores.data.0.grab_profile.site_postal_code', '528882')
            ->where('stores.data.0.grab_profile.match', true)
            ->whereNot('grabProfilesCheckedAt', null));

        // The same read again: nothing new in the store's history.
        $this->send([['merchant_id' => self::STORE, 'text' => self::PAGE]])->assertJson(['read' => 1, 'changed' => 0]);
        $this->assertSame(1, DeliveryPlatformStoreEvent::query()->where('event', DeliveryPlatformStoreEvent::EVENT_GRAB_PROFILE)->count());
    }

    public function test_grab_showing_another_postal_code_is_flagged(): void
    {
        $this->send([['merchant_id' => strtolower(self::STORE), 'name' => 'Jurong Play Grounds', 'address' => '50 Jurong West, 649406, Singapore']])
            ->assertOk()->assertJson(['mismatched' => [self::STORE]]);

        $this->actingAs($this->user)->get('/grab-stores')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stores.data.0.grab_profile.postal_code', '649406')
            ->where('stores.data.0.grab_profile.match', false));
    }

    public function test_grab_already_showing_the_new_site_clears_the_site_changed_note_and_closes_the_request(): void
    {
        $binding = $this->ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->sole();
        $ferraria = $this->site('Ferraria Park Condo', '15960', '506889');
        $binding->vend->forceFill(['customer_id' => $ferraria->id])->save();
        $changes = app(GrabStoreChangeRequests::class);
        $changes->requested($this->ref, ['kind' => 'address', 'route' => 'app', 'address' => '20 Flora Drive'], $this->user->id);
        $this->assertNotNull($binding->fresh()->siteChangedFrom());

        // Still the old address on Grab: the note stays.
        $this->send([['merchant_id' => self::STORE, 'text' => self::PAGE]])->assertJson(['site_confirmed' => [], 'mismatched' => [self::STORE]]);
        $this->assertNotNull($binding->fresh()->siteChangedFrom());

        // Changed in the GrabMerchant app: the next read sees it.
        $this->send([['merchant_id' => self::STORE, 'address' => '20, Flora Drive, Ferraria Park, 506889, Singapore']])
            ->assertJson(['site_confirmed' => [self::STORE], 'mismatched' => []]);
        $this->assertNull($binding->fresh()->siteChangedFrom());
        $this->assertNull($changes->open(collect([$this->ref->id]))[$this->ref->id] ?? null, 'the address request is done');
    }

    public function test_a_moved_machine_with_no_request_is_confirmed_too(): void
    {
        $binding = $this->ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->sole();
        $ferraria = $this->site('Ferraria Park Condo', '15960', '506889');
        $binding->vend->forceFill(['customer_id' => $ferraria->id])->save();

        $this->send([['merchant_id' => self::STORE, 'address' => '20, Flora Drive, 506889, Singapore']])->assertJson(['site_confirmed' => [self::STORE]]);

        $this->assertSame($ferraria->id, (int) $binding->fresh()->customer_id);
        $event = DeliveryPlatformStoreEvent::query()->where('event', DeliveryPlatformStoreEvent::EVENT_GRAB_PROFILE)->sole();
        $this->assertEquals(['from' => $this->tampines->id, 'to' => $ferraria->id], $event->data['site']);
    }

    public function test_unknown_stores_and_other_operators_stores_are_only_counted(): void
    {
        $other = DeliveryPlatformRefNumber::forceCreate(['ref_number' => '4-OTHEROPERATOR1', 'operator_id' => 999999, 'delivery_platform_id' => $this->ref->delivery_platform_id]);

        $this->send([
            ['merchant_id' => '4-NOTONFILE00001', 'address' => '1 Road, 123456'],
            ['merchant_id' => $other->ref_number, 'address' => '1 Road, 123456'],
        ])->assertJson(['read' => 0, 'unknown' => ['4-NOTONFILE00001', '4-OTHEROPERATOR1']]);
        $this->assertNull($other->fresh()->grab_address);
    }

    public function test_only_staff_who_may_change_stores_can_send_a_read(): void
    {
        $reader = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $reader->givePermissionTo('read delivery-platform-vends');

        $this->actingAs($reader)->postJson('/grab-stores/grab-profiles', ['stores' => [['merchant_id' => self::STORE, 'text' => self::PAGE]]])->assertForbidden();
    }
}
