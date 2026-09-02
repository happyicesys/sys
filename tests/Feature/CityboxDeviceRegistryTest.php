<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxDevice;
use App\Models\Vend;
use App\Services\Citybox\CityboxDeviceRegistry;
use App\Services\Citybox\DeviceProvisioningService;
use App\Services\Citybox\DeviceSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

/**
 * citybox_devices is the fleet as last reported by box_list — written only by
 * the poller/refresh, read by the Create-page picker. Rows are never deleted.
 */
class CityboxDeviceRegistryTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        \Illuminate\Support\Carbon::setTestNow();
        parent::tearDown();
    }

    private function registry(): CityboxDeviceRegistry
    {
        return app(CityboxDeviceRegistry::class);
    }

    public function test_poll_upserts_every_device_linked_or_not_and_keeps_first_seen(): void
    {
        $this->gw->seedDevice('E1', 'Singapore1', online: 0)->seedDevice('E2', 'Singapore2', 'visual-8');
        Vend::create(['code' => 5, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E1', 'is_active' => 1]);

        \Illuminate\Support\Carbon::setTestNow('2026-09-02 10:00:00');
        app(DeviceSyncService::class)->syncFleet();

        $this->assertSame(2, CityboxDevice::count());
        $e2 = CityboxDevice::where('equipment_id', 'E2')->first();
        $this->assertSame('visual-8', $e2->type);
        $this->assertTrue($e2->online);
        $this->assertSame(1, $e2->ops_status);
        $this->assertSame('2026-09-02 10:00:00', $e2->first_seen_at->toDateTimeString());

        // Second sweep: state changes are applied, first_seen_at is untouched, last_seen_at advances.
        \Illuminate\Support\Carbon::setTestNow('2026-09-02 10:01:00');
        $this->gw->seedDevice('E2', 'Singapore2 renamed', 'visual-8', online: 0, status: 0);
        app(DeviceSyncService::class)->syncFleet();

        $e2->refresh();
        $this->assertSame(2, CityboxDevice::count());
        $this->assertSame('Singapore2 renamed', $e2->name);
        $this->assertFalse($e2->online);
        $this->assertSame(0, $e2->ops_status);
        $this->assertSame('2026-09-02 10:00:00', $e2->first_seen_at->toDateTimeString());
        $this->assertSame('2026-09-02 10:01:00', $e2->last_seen_at->toDateTimeString());
    }

    public function test_unlinked_is_table_served_and_excludes_adopted_and_vanished_devices(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-09-02 10:00:00');
        $this->gw->seedDevice('E1')->seedDevice('E2')->seedDevice('E3');
        Vend::create(['code' => 5, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E1', 'is_active' => 1]);

        $this->assertSame(['E2', 'E3'], $this->registry()->unlinked(fresh: true)->map->equipmentId->all());

        // E3 drops out of their list: still a row (history), no longer offered.
        unset($this->gw->devices['E3']);
        \Illuminate\Support\Carbon::setTestNow('2026-09-02 10:02:00');
        $this->assertSame(['E2'], $this->registry()->unlinked(fresh: true)->map->equipmentId->all());
        $this->assertSame(3, CityboxDevice::count());
        $this->assertNull($this->registry()->find('E3'));
        $this->assertSame('E2', $this->registry()->find('E2')?->equipmentId);
    }

    public function test_an_empty_complete_listing_does_not_empty_the_fleet(): void
    {
        $this->gw->seedDevice('E1')->seedDevice('E2');
        $this->registry()->refresh();
        $this->assertSame(2, CityboxDevice::inFleet()->count());

        // Transient bad response: their API answers with nothing. Rows keep in_fleet.
        $this->gw->devices = [];
        $this->registry()->refresh();
        $this->assertSame(2, CityboxDevice::inFleet()->count());

        // A real (non-empty) sweep still retires what it does not list.
        $this->gw->seedDevice('E1');
        $this->registry()->refresh();
        $this->assertSame(['E1'], CityboxDevice::inFleet()->pluck('equipment_id')->all());
    }

    public function test_stale_registry_refreshes_itself_and_a_fresh_one_does_not_call_the_api(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-09-02 10:00:00');
        $this->gw->seedDevice('E1');
        $this->assertTrue($this->registry()->isStale()); // empty table
        $this->assertSame(['E1'], $this->registry()->unlinked()->map->equipmentId->all());
        $this->assertFalse($this->registry()->isStale());

        // Fleet changes but the table is fresh (<5 min): served from the table, API not consulted.
        $this->gw->seedDevice('E9');
        \Illuminate\Support\Carbon::setTestNow('2026-09-02 10:03:00');
        $this->assertSame(['E1'], $this->registry()->unlinked()->map->equipmentId->all());

        // Past the freshness window: the read refreshes first.
        \Illuminate\Support\Carbon::setTestNow('2026-09-02 10:06:00');
        $this->assertTrue($this->registry()->isStale());
        $this->assertSame(['E1', 'E9'], $this->registry()->unlinked()->map->equipmentId->sort()->values()->all());
    }

    public function test_provisioning_picker_reads_the_registry(): void
    {
        $this->gw->seedDevice('E1', 'Singapore1')->seedDevice('E2', 'Singapore2');
        Vend::create(['code' => 5, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E1', 'is_active' => 1]);

        $r = app(DeviceProvisioningService::class)->devices();

        $this->assertSame(['E2'], $r['unlinked']->map->equipmentId->all());
        $this->assertArrayHasKey('E1', $r['linked']);
        $this->assertSame('Singapore2', app(DeviceProvisioningService::class)->device('E2')?->name);
        $this->assertSame(2, CityboxDevice::count());
    }

    public function test_refresh_one_updates_the_registry_row(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-09-02 10:00:00');
        $this->gw->seedDevice('E1', online: 0);
        $vend = Vend::create(['code' => 5, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E1', 'is_active' => 1]);
        app(DeviceSyncService::class)->syncFleet();
        $this->assertFalse(CityboxDevice::where('equipment_id', 'E1')->first()->online);

        $this->gw->seedDevice('E1', online: 1);
        app(DeviceSyncService::class)->refreshOne($vend);

        $this->assertTrue(CityboxDevice::where('equipment_id', 'E1')->first()->online);
        $this->assertTrue((bool) $vend->fresh()->is_online);
    }
}
