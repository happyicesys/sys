<?php

namespace Tests\Feature;

use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use App\Models\User;
use App\Models\Vend;
use App\Services\GrabStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * Grab puts "[CLOSED]" in front of a store's name when it retires the store, and such a store can never be
 * activated again (Brian, 2026-11-17). ConnectVend refuses to put it on a machine, move it, resume it or switch
 * it on, and the daily GrabMerchant read switches it off.
 */
class GrabClosedStoreTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const STORE = '4-C2B3JJAWN2LHMA';

    private const CLOSED_NAME = '[CLOSED] GoWell Fruit Ice Bar Vending Machine (Unmanned Store) - Westgate';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);
        Queue::fake();
        Http::fake(fn ($request) => str_ends_with($request->url(), '/merchant/pause') ? Http::response(null, 204) : Http::response([], 200));

        foreach (['read delivery-platform-vends', 'update delivery-platform-vends'] as $permission) {
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
        return Vend::forceCreate(['code' => $code, 'operator_id' => $this->grabMapping->operator_id]);
    }

    private function closeOnGrab(DeliveryPlatformRefNumber $ref): DeliveryPlatformRefNumber
    {
        $ref->forceFill(['grab_store_name' => self::CLOSED_NAME])->save();

        return $ref->fresh();
    }

    private function assertRefused(callable $action): void
    {
        try {
            $action();
            $this->fail('A store Grab has closed was let through.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Grab has closed store', collect($e->errors())->flatten()->first());
        }
    }

    public function test_the_name_decides_closed(): void
    {
        $this->assertTrue(DeliveryPlatformRefNumber::nameMeansClosed(self::CLOSED_NAME));
        $this->assertTrue(DeliveryPlatformRefNumber::nameMeansClosed(' [closed] Store'));
        $this->assertFalse(DeliveryPlatformRefNumber::nameMeansClosed('Ice Cream Vending Machine (Unmanned Store) - Closed Walk'));
        $this->assertFalse(DeliveryPlatformRefNumber::nameMeansClosed(null));
    }

    public function test_a_closed_store_cannot_go_on_a_machine_move_resume_or_switch_on(): void
    {
        $binding = $this->bindGrabStore(2870, self::STORE, [11 => ['qty' => 5, 'capacity' => 10]]);
        $this->service()->pause($binding, $this->user->id, 'Repair');
        $ref = $this->closeOnGrab($binding->deliveryPlatformRefNumber);

        $this->assertRefused(fn () => $this->service()->resume($binding->fresh(), $this->user->id));
        $this->assertRefused(fn () => $this->service()->move($binding->fresh(), $this->machine(2666), null, $this->user->id));

        // Switching off and taking it off its machine still work.
        $this->service()->setStatus($ref, false, $this->user->id);
        $this->assertRefused(fn () => $this->service()->setStatus($ref->fresh(), true, $this->user->id));
        $this->assertSame(DeliveryPlatformRefNumber::STATUS_INACTIVE, (int) $ref->fresh()->status);

        $this->service()->unbind($binding->fresh(), $this->user->id, 'store_closed');
        $this->assertNotNull($binding->fresh()->end_date);
        $this->assertRefused(fn () => $this->service()->bind($ref->fresh(), $this->machine(2667), $this->grabMapping, $this->user->id));
        $this->assertSame(0, DeliveryProductMappingVend::query()->withoutGlobalScopes()->whereNull('end_date')->count());
    }

    public function test_a_due_pause_on_a_closed_store_stays_paused_and_others_still_resume(): void
    {
        $closed = $this->bindGrabStore(2870, self::STORE, [11 => ['qty' => 5, 'capacity' => 10]]);
        $this->service()->pause($closed, $this->user->id, 'Restock', now()->addHour());
        $this->closeOnGrab($closed->deliveryPlatformRefNumber);
        $open = $this->bindGrabStore(2871, '4-C6DGT3CUWEXGG2', [11 => ['qty' => 5, 'capacity' => 10]]);
        $this->service()->pause($open, $this->user->id, 'Restock', now()->addHour());

        $this->travel(61)->minutes();
        $this->artisan('grab:resume-due-pauses')->assertSuccessful();

        $this->assertFalse((bool) $closed->fresh()->is_active);
        $this->assertTrue((bool) $open->fresh()->is_active);
    }

    public function test_the_grabmerchant_read_switches_a_closed_store_off_and_the_page_badges_it(): void
    {
        $binding = $this->bindGrabStore(2870, self::STORE, [11 => ['qty' => 5, 'capacity' => 10]]);
        $page = self::CLOSED_NAME."\nEdit\nDelivery hours\n•\nMon-Sun, 12:00 AM-11:59 PM\nEdit\n3 Gateway Dr, #03-13, Jurong East, 608532, 608532, Singapore\nEdit\nStore ID\n".self::STORE;

        $this->actingAs($this->user)->postJson('/grab-stores/grab-profiles', ['stores' => [['merchant_id' => self::STORE, 'text' => $page]]])->assertOk();

        $ref = $binding->deliveryPlatformRefNumber->fresh();
        $this->assertSame(DeliveryPlatformRefNumber::STATUS_INACTIVE, (int) $ref->status);
        $this->assertFalse($binding->fresh()->isSelling());

        $this->actingAs($this->user)->get('/grab-stores')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('stores.data.0.closed_by_grab', true));

        $this->actingAs($this->user)->postJson('/grab-stores/'.$ref->id.'/status', ['active' => true])->assertStatus(422);
    }
}
