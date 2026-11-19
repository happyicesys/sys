<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\DeliveryProductMappingVend;
use App\Models\Vend;
use App\Services\Grab\GrabSiteGuard;
use App\Services\GrabStoreService;
use App\Support\Grab\GrabStoreHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * A machine leaving the Site its Grab store was set up at stops the store (Brian, 2026-11-19), and the Operation
 * Dashboard shows such a store red and can filter for it (GrabSiteGuard, GrabStoreHealth).
 */
class GrabSiteGuardTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const STORE = '4-C6VGNELVVNAZN6';

    private DeliveryProductMappingVend $binding;

    private Customer $siteA;

    private Customer $siteB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        Queue::fake();
        Http::fake();
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);
        $operatorId = $this->grabMapping->operator_id;
        $this->siteA = Customer::forceCreate(['name' => 'Tampines SAFRA', 'code' => '15895', 'operator_id' => $operatorId]);
        $this->siteB = Customer::forceCreate(['name' => 'Ferraria Park Condo', 'code' => '15960', 'operator_id' => $operatorId]);

        Vend::forceCreate(['code' => 2338, 'operator_id' => $operatorId, 'customer_id' => $this->siteA->id]);
        $this->binding = $this->bindGrabStore(2338, self::STORE, [11 => ['qty' => 5, 'capacity' => 10]]);
        $this->binding->forceFill(['customer_id' => $this->siteA->id])->save();
    }

    private function vend(): Vend
    {
        return Vend::query()->withoutGlobalScopes()->findOrFail($this->binding->vend_id);
    }

    private function pausedEvents()
    {
        return DeliveryPlatformStoreEvent::query()->where('event', DeliveryPlatformStoreEvent::EVENT_PAUSED)->get();
    }

    public function test_unbinding_the_machine_from_its_site_pauses_the_store_on_grab(): void
    {
        $vend = $this->vend();
        $vend->customer_id = null;
        $vend->save();

        $binding = $this->binding->fresh();
        $this->assertFalse((bool) $binding->is_active);
        $this->assertNull($binding->end_date, 'the store stays on the machine, paused');
        $this->assertStringContainsString('unbound from Site 15895 Tampines SAFRA', $binding->pause_reason);
        $event = $this->pausedEvents()->sole();
        $this->assertEquals(['from' => $this->siteA->id, 'to' => null], $event->data['site_change']);
        Http::assertSent(fn ($request) => ($request->data()['merchantID'] ?? null) === self::STORE && ($request->data()['isPause'] ?? null) === true);
    }

    public function test_moving_the_machine_to_another_site_pauses_the_store_and_the_badge_turns_red(): void
    {
        $this->assertSame(GrabStoreHealth::SELLING, GrabStoreHealth::forVends([$this->binding->vend_id])[$this->binding->vend_id][0]['state']);

        $vend = $this->vend();
        $vend->customer_id = $this->siteB->id;
        $vend->save();

        $this->assertFalse((bool) $this->binding->fresh()->is_active);
        $this->assertStringContainsString('moved from Site 15895 Tampines SAFRA to 15960 Ferraria Park Condo', $this->binding->fresh()->pause_reason);

        $store = GrabStoreHealth::forVends([$vend->id])[$vend->id][0];
        $this->assertSame(GrabStoreHealth::PROBLEM, $store['state']);
        $this->assertSame(self::STORE, $store['merchant_id']);
        $this->assertCount(2, $store['reasons'], 'paused, and the machine left the Site Grab may still show');
        $this->assertStringContainsString('15895 Tampines SAFRA', $store['reasons'][1]);
    }

    public function test_the_machine_back_at_the_stores_site_resumes_it(): void
    {
        $vend = $this->vend();
        $vend->customer_id = null;
        $vend->save();
        $vend->customer_id = $this->siteA->id;
        $vend->save();

        $this->assertTrue((bool) $this->binding->fresh()->is_active);
        $resumed = DeliveryPlatformStoreEvent::query()->where('event', DeliveryPlatformStoreEvent::EVENT_RESUMED)->sole();
        $this->assertSame('settled', $resumed->data['site_change']);
    }

    public function test_a_store_a_person_paused_is_never_resumed_by_a_site_change(): void
    {
        app(GrabStoreService::class)->pause($this->binding, null, 'Machine being serviced');
        $vend = $this->vend();
        $vend->customer_id = null;
        $vend->save();
        $vend->customer_id = $this->siteA->id;
        $vend->save();

        $this->assertFalse((bool) $this->binding->fresh()->is_active);
        $this->assertSame('Machine being serviced', $this->binding->fresh()->pause_reason);
        $this->assertCount(1, $this->pausedEvents(), 'already paused: no second pause either');
    }

    public function test_the_new_site_recorded_on_the_store_resumes_it(): void
    {
        $vend = $this->vend();
        $vend->customer_id = $this->siteB->id;
        $vend->save();
        $this->assertFalse((bool) $this->binding->fresh()->is_active);

        // What Update on Grab → Done (or the GrabMerchant check) does once Grab shows the new address.
        $this->binding->fresh()->forceFill(['customer_id' => $this->siteB->id])->save();
        $this->assertTrue(app(GrabSiteGuard::class)->resumeIfSettled($this->binding->fresh(), null, 'Grab shows the new address'));
        $this->assertTrue((bool) $this->binding->fresh()->is_active);
        $this->assertSame(GrabStoreHealth::SELLING, GrabStoreHealth::forVends([$vend->id])[$vend->id][0]['state']);
    }

    public function test_a_binding_with_no_site_on_record_takes_the_site_the_machine_left(): void
    {
        $this->binding->forceFill(['customer_id' => null])->save();
        $vend = $this->vend();
        $vend->customer_id = $this->siteB->id;
        $vend->save();

        $binding = $this->binding->fresh();
        $this->assertSame($this->siteA->id, (int) $binding->customer_id);
        $this->assertFalse((bool) $binding->is_active);
        $this->assertSame('15895 Tampines SAFRA', $binding->siteChangedFrom());
    }

    public function test_a_machine_gaining_its_first_site_changes_nothing(): void
    {
        $vend = Vend::forceCreate(['code' => 2339, 'operator_id' => $this->grabMapping->operator_id]);
        $other = $this->bindGrabStore(2339, '4-OTHERSTORE', [12 => ['qty' => 1, 'capacity' => 5]]);
        $vend->refresh();
        $vend->customer_id = $this->siteB->id;
        $vend->save();

        $this->assertTrue((bool) $other->fresh()->is_active);
        $this->assertCount(0, $this->pausedEvents());
    }

    public function test_the_filter_and_the_badge_agree_on_every_red_case(): void
    {
        $vendId = $this->binding->vend_id;
        $filtered = function (string $state) use ($vendId) {
            $query = Vend::query()->withoutGlobalScopes()->whereKey($vendId);
            GrabStoreHealth::applyFilter($query, $state);

            return $query->exists();
        };
        $badge = fn () => GrabStoreHealth::forVends([$vendId])[$vendId][0]['state'];
        $assertState = function (string $state) use ($filtered, $badge) {
            $this->assertSame($state, $badge());
            $this->assertTrue($filtered($state));
            $this->assertFalse($filtered($state === GrabStoreHealth::SELLING ? GrabStoreHealth::PROBLEM : GrabStoreHealth::SELLING));
            $this->assertFalse($filtered('none'));
        };

        $assertState(GrabStoreHealth::SELLING);

        // Paused by hand.
        DeliveryProductMappingVend::query()->withoutGlobalScopes()->whereKey($this->binding->id)->update(['is_active' => false]);
        $assertState(GrabStoreHealth::PROBLEM);
        DeliveryProductMappingVend::query()->withoutGlobalScopes()->whereKey($this->binding->id)->update(['is_active' => true]);
        $assertState(GrabStoreHealth::SELLING);

        // Store switched off.
        DeliveryPlatformRefNumber::query()->whereKey($this->binding->delivery_platform_ref_number_id)->update(['status' => DeliveryPlatformRefNumber::STATUS_INACTIVE]);
        $assertState(GrabStoreHealth::PROBLEM);
        DeliveryPlatformRefNumber::query()->whereKey($this->binding->delivery_platform_ref_number_id)->update(['status' => DeliveryPlatformRefNumber::STATUS_ACTIVE]);

        // Machine moved Site without the listener (a raw update): still red.
        Vend::query()->withoutGlobalScopes()->whereKey($vendId)->update(['customer_id' => $this->siteB->id]);
        $assertState(GrabStoreHealth::PROBLEM);

        // Machine on no Site.
        Vend::query()->withoutGlobalScopes()->whereKey($vendId)->update(['customer_id' => null]);
        $assertState(GrabStoreHealth::PROBLEM);

        // A machine with no Grab store is "none".
        $plain = Vend::forceCreate(['code' => 2400, 'operator_id' => $this->grabMapping->operator_id]);
        $query = Vend::query()->withoutGlobalScopes()->whereKey($plain->id);
        GrabStoreHealth::applyFilter($query, 'none');
        $this->assertTrue($query->exists());
    }
}
