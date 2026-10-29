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
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * Grab Stores (plan §9 step 1, Brian 2026-10-29): a merchantID moves between
 * machines over time; each binding is one dated period, never edited in place, with
 * at most one open per store and per machine, and every change is a store event.
 */
class GrabStoresTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const MERCHANT = '4-C7AFJUJUBAEXG6';

    private User $user;

    private bool $grabKnowsStore = true;

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);
        Queue::fake();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/store/status')) {
                return $this->grabKnowsStore
                    ? Http::response(['isOpen' => true, 'closeReason' => '', 'isInSpecialOpeningHourRange' => false], 200)
                    : Http::response(['message' => 'not found'], 404);
            }

            return str_ends_with($request->url(), '/merchant/pause') ? Http::response(null, 204) : Http::response([], 200);
        });

        foreach (['read delivery-platform-vends', 'update delivery-platform-vends', 'read delivery-product-mappings'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->user = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $this->user->givePermissionTo(['read delivery-platform-vends', 'update delivery-platform-vends']);
    }

    private function service(): GrabStoreService
    {
        return app(GrabStoreService::class);
    }

    private function machine(int $code): Vend
    {
        return Vend::query()->withoutGlobalScopes()->where('code', $code)->first()
            ?? Vend::forceCreate(['code' => $code, 'operator_id' => $this->grabMapping->operator_id]);
    }

    private function pauseCalls(?bool $isPause = null): int
    {
        return collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/merchant/pause')
            && ($isPause === null || $pair[0]['isPause'] === $isPause))->count();
    }

    public function test_a_move_closes_the_old_period_and_opens_the_new_one_at_the_same_instant(): void
    {
        $first = $this->bindGrabStore(2867, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);

        $second = $this->service()->move($first, $this->machine(2666), null, $this->user->id, 'moved', 'Site closed');

        $first->refresh();
        $this->assertNotNull($first->end_date);
        $this->assertEquals($first->end_date, $second->start_date);
        $this->assertSame('moved', $first->unbind_reason);
        $this->assertSame($this->user->id, (int) $first->unbound_by);
        $this->assertSame($this->user->id, (int) $second->bound_by);
        $this->assertSame(2666, (int) $second->vend_code);
        $this->assertSame($first->delivery_platform_ref_number_id, $second->delivery_platform_ref_number_id);
        $this->assertTrue($second->isSelling());
        // The store stays open on Grab through a move.
        $this->assertSame(0, $this->pauseCalls());

        $events = DeliveryPlatformStoreEvent::query()->orderBy('id')->pluck('event')->all();
        $this->assertSame(['unbound', 'bound'], $events);
    }

    public function test_a_paused_store_stays_paused_on_its_new_machine(): void
    {
        $first = $this->bindGrabStore(2867, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);
        $this->service()->pause($first, $this->user->id, 'Repair');

        $second = $this->service()->move($first->fresh(), $this->machine(2666), null, $this->user->id);

        $this->assertFalse((bool) $second->is_active);
        $this->assertSame('Repair', $second->pause_reason);
    }

    public function test_a_store_already_on_a_machine_is_refused_a_second_one(): void
    {
        $binding = $this->bindGrabStore(2867, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);

        $this->expectException(ValidationException::class);
        $this->service()->bind($binding->deliveryPlatformRefNumber, $this->machine(2666), $this->grabMapping, $this->user->id);
    }

    public function test_a_machine_already_carrying_a_store_is_refused_another(): void
    {
        $this->bindGrabStore(2867, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);
        $other = DeliveryPlatformRefNumber::forceCreate([
            'delivery_platform_id' => 1, 'operator_id' => $this->grabMapping->operator_id, 'ref_number' => '4-OTHER',
        ]);

        try {
            $this->service()->bind($other, $this->machine(2867), $this->grabMapping, $this->user->id);
            $this->fail('A second store was bound to machine 2867.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(self::MERCHANT, $e->getMessage());
        }
        $this->assertSame(1, DeliveryProductMappingVend::query()->withoutGlobalScopes()->whereNull('end_date')->count());
    }

    public function test_removing_a_store_from_its_machine_pauses_it_on_grab_and_keeps_the_row(): void
    {
        $binding = $this->bindGrabStore(2867, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);

        $this->service()->unbind($binding, $this->user->id, 'store_closed', 'Landlord');

        $binding->refresh();
        $this->assertNotNull($binding->end_date);
        $this->assertSame('store_closed', $binding->unbind_reason);
        $this->assertSame('Landlord', $binding->unbind_remarks);
        $this->assertSame(1, $this->pauseCalls(true));
        $this->assertSame(1, DeliveryProductMappingVend::query()->withoutGlobalScopes()->count());
    }

    public function test_putting_a_removed_store_back_on_a_machine_reopens_it_on_grab(): void
    {
        $binding = $this->bindGrabStore(2867, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);
        $ref = $binding->deliveryPlatformRefNumber;
        $this->service()->unbind($binding, $this->user->id);

        $this->service()->bind($ref, $this->machine(2869), $this->grabMapping, $this->user->id);

        $this->assertSame(1, $this->pauseCalls(false));
        $this->assertSame(2, DeliveryProductMappingVend::query()->withoutGlobalScopes()->where('delivery_platform_ref_number_id', $ref->id)->count());
    }

    public function test_closing_a_leftover_second_binding_does_not_pause_the_live_store(): void
    {
        // Prod 4-C7CDNZDXKEBJPE: selling on 2873, still open (paused) on 2658.
        $live = $this->bindGrabStore(2873, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);
        $leftover = DeliveryProductMappingVend::forceCreate([
            'delivery_product_mapping_id' => $this->grabMapping->id,
            'vend_id' => $this->machine(2658)->id,
            'vend_code' => 2658,
            'platform_ref_id' => self::MERCHANT,
            'delivery_platform_ref_number_id' => $live->delivery_platform_ref_number_id,
            'is_active' => false,
        ]);

        $this->service()->unbind($leftover, $this->user->id, 'correction');

        $this->assertSame(0, $this->pauseCalls(true));
        $this->assertTrue($live->fresh()->isSelling());
    }

    public function test_pause_and_resume_are_recorded_and_sent_to_grab(): void
    {
        $binding = $this->bindGrabStore(2867, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);

        $this->service()->pause($binding, $this->user->id, 'Repair', now()->addHours(4));
        $this->assertFalse($binding->fresh()->isSelling());
        $this->service()->resume($binding->fresh(), $this->user->id);

        $this->assertTrue($binding->fresh()->isSelling());
        $this->assertNull($binding->fresh()->paused_until);
        $this->assertSame(1, $this->pauseCalls(true));
        $this->assertSame(1, $this->pauseCalls(false));
        $this->assertSame(['paused', 'resumed'], DeliveryPlatformStoreEvent::query()->orderBy('id')->pluck('event')->all());
    }

    public function test_a_pause_with_an_until_comes_back_by_itself(): void
    {
        $binding = $this->bindGrabStore(2867, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);
        $this->service()->pause($binding, $this->user->id, 'Restock', now()->addHour());

        $this->artisan('grab:resume-due-pauses')->assertSuccessful();
        $this->assertFalse((bool) $binding->fresh()->is_active);

        $this->travel(61)->minutes();
        $this->artisan('grab:resume-due-pauses')->assertSuccessful();

        $this->assertTrue((bool) $binding->fresh()->is_active);
        $event = DeliveryPlatformStoreEvent::query()->latest('id')->first();
        $this->assertSame('resumed', $event->event);
        $this->assertNull($event->user_id);
    }

    public function test_switching_a_store_off_pauses_it_on_grab_at_once_and_on_reopens_it(): void
    {
        $binding = $this->bindGrabStore(2867, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);
        $ref = $binding->deliveryPlatformRefNumber;

        $this->service()->setStatus($ref, false, $this->user->id);
        $this->assertFalse($binding->fresh()->isSelling());
        $this->assertSame(1, $this->pauseCalls(true));

        $this->service()->setStatus($ref->fresh(), true, $this->user->id);
        $this->assertTrue($binding->fresh()->isSelling());
        $this->assertSame(1, $this->pauseCalls(false));
    }

    public function test_a_merchant_id_with_or_without_its_prefix_is_one_store(): void
    {
        $this->service()->addStore($this->grabMapping->operator_id, '4-C7CCRTEJJYUJR6', null, $this->user->id);

        $this->expectException(ValidationException::class);
        $this->service()->addStore($this->grabMapping->operator_id, 'c7ccrtejjyujr6', null, $this->user->id);
    }

    public function test_add_store_asks_grab_first_and_can_skip_it(): void
    {
        $this->grabKnowsStore = false;

        try {
            $this->service()->addStore($this->grabMapping->operator_id, '4-UNKNOWN', null, $this->user->id);
            $this->fail('A store Grab does not know was saved without the tick.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('404', $e->getMessage());
        }

        $ref = $this->service()->addStore($this->grabMapping->operator_id, '4-UNKNOWN', null, $this->user->id, skipGrabCheck: true);
        $this->assertSame(DeliveryPlatformRefNumber::STATUS_ACTIVE, (int) $ref->status);
    }

    public function test_the_old_template_page_unbind_and_bind_go_through_the_writer(): void
    {
        $binding = $this->bindGrabStore(2867, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);

        $this->actingAs($this->user)->delete("/delivery-product-mappings/unbind/{$binding->id}")->assertRedirect();
        $this->assertNotNull($binding->fresh()->end_date);
        $this->assertSame($this->user->id, (int) $binding->fresh()->unbound_by);

        $this->actingAs($this->user)->post("/delivery-product-mappings/{$this->grabMapping->id}/bind-vend", [
            'platform_ref_id' => self::MERCHANT,
            'vend_id' => $this->machine(2869)->id,
            'delivery_platform_ref_number_id' => $binding->delivery_platform_ref_number_id,
        ])->assertRedirect();

        $open = DeliveryProductMappingVend::query()->withoutGlobalScopes()->whereNull('end_date')->get();
        $this->assertCount(1, $open);
        $this->assertSame(2869, (int) $open->first()->vend_code);
        $this->assertSame($this->user->id, (int) $open->first()->bound_by);
    }

    public function test_a_template_still_on_a_machine_cannot_be_deleted_and_history_survives_a_delete(): void
    {
        $binding = $this->bindGrabStore(2867, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);
        Permission::findOrCreate('delete delivery-platform-product-mappings', 'web');

        $this->actingAs($this->user)->delete("/delivery-product-mappings/{$this->grabMapping->id}")->assertSessionHasErrors('delete');
        $this->assertNotNull($this->grabMapping->fresh());

        $this->service()->unbind($binding, $this->user->id);
        $this->actingAs($this->user)->delete("/delivery-product-mappings/{$this->grabMapping->id}")->assertRedirect();

        $this->assertNull($this->grabMapping->fresh());
        $this->assertSame(1, DeliveryProductMappingVend::query()->withoutGlobalScopes()->whereKey($binding->id)->count());
    }

    public function test_the_page_lists_one_row_per_store_with_its_status_and_history(): void
    {
        $selling = $this->bindGrabStore(2867, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);
        $paused = $this->bindGrabStore(2868, '4-PAUSED', [11 => ['qty' => 5, 'capacity' => 10]]);
        $this->service()->pause($paused, $this->user->id, 'Repair');
        DeliveryPlatformRefNumber::forceCreate([
            'delivery_platform_id' => 1, 'operator_id' => $this->grabMapping->operator_id, 'ref_number' => '4-SPARE',
        ]);

        $this->actingAs($this->user)->get('/grab-stores')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('GrabStore/Index')
                ->where('totals.all', 3)
                ->where('totals.selling', 1)
                ->where('totals.paused', 1)
                ->where('totals.no_machine', 1)
                ->has('stores.data', 3));

        $this->actingAs($this->user)->get('/grab-stores?status=paused')
            ->assertInertia(fn ($page) => $page->has('stores.data', 1)->where('stores.data.0.ref_number', '4-PAUSED')
                ->where('stores.data.0.binding.pause_reason', 'Repair'));

        $this->actingAs($this->user)->getJson("/grab-stores/{$selling->delivery_platform_ref_number_id}/history")
            ->assertOk()
            ->assertJsonPath('bindings.0.vend_code', 2867)
            ->assertJsonPath('store.ref_number', self::MERCHANT);
    }

    public function test_the_move_endpoint_needs_update_rights(): void
    {
        $binding = $this->bindGrabStore(2867, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);
        $reader = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $reader->givePermissionTo('read delivery-platform-vends');

        $this->actingAs($reader)->post("/grab-stores/bindings/{$binding->id}/move", ['vend_code' => '2666', 'reason' => 'moved'])
            ->assertForbidden();
        $this->assertNull($binding->fresh()->end_date);
    }
}
