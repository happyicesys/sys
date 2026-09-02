<?php

namespace Tests\Unit;

use App\Enums\Citybox\DeviceOpsStatus;
use App\Enums\Citybox\DeviceState;
use App\Models\Vend;
use App\Services\Citybox\ChillerStatus;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * ChillerStatus is the read-only "CityBox side" of a chiller's state, built
 * from the last poll stored on the vend — never from a live call.
 * Boots the app (Eloquent datetime casts resolve the connection's date
 * format) but runs no queries.
 */
class CityboxChillerStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function vend(array $attrs = []): Vend
    {
        $vend = new Vend;
        $vend->forceFill(array_merge([
            'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'citybox_equipment_id' => 'ICB26F9FUPE7',
            'is_online' => 1,
            'citybox_synced_at' => '2026-09-02 10:00:00',
            'citybox_status_json' => [
                'source' => 'openapi', 'equipment_status' => 1, 'equipment_status_str' => '启运',
                'online' => true, 'device_type' => 'visual-2', 'name' => '#1',
                'heartbeat_last_recovery' => '2026-08-19 01:14:36', 'heartbeat_last_offline' => '2026-08-12 19:12:37',
            ],
        ], $attrs));

        return $vend;
    }

    public function test_builds_their_view_from_the_stored_poll(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 10:02:00');
        $s = ChillerStatus::forVend($this->vend());

        $this->assertSame('ICB26F9FUPE7', $s->equipmentId);
        $this->assertSame(DeviceOpsStatus::Running, $s->opsStatus);
        $this->assertTrue($s->isRunning());
        $this->assertFalse($s->isRetired());
        $this->assertTrue($s->online);
        $this->assertTrue($s->isKnown());
        $this->assertFalse($s->isStale());
        $this->assertSame('CityBox F5 (visual-2)', $s->toArray()['model']);
        $this->assertSame('Running (启运) · online · synced 2 minutes ago', $s->summary());
    }

    public function test_stale_after_five_minutes_without_a_poll(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 10:06:00');
        $s = ChillerStatus::forVend($this->vend());

        $this->assertTrue($s->isStale());
        $this->assertStringContainsString('STALE', $s->summary());
    }

    public function test_retired_is_exposed_as_a_predicate_but_never_written_back(): void
    {
        $vend = $this->vend(['is_active' => 1, 'citybox_status_json' => ['equipment_status' => '99', 'equipment_status_str' => '已撤机']]);
        $s = ChillerStatus::forVend($vend);

        $this->assertTrue($s->isRetired());
        $this->assertFalse($s->isRunning());
        $this->assertSame(1, $vend->is_active); // the layer reads; it does not decide mark1's Status
    }

    public function test_unknown_status_string_falls_back_to_their_label_then_unknown(): void
    {
        $s = ChillerStatus::forVend($this->vend(['citybox_status_json' => ['equipment_status' => 'x', 'equipment_status_str' => '维护中']]));
        $this->assertSame('维护中', $s->opsLabel());

        $s = ChillerStatus::forVend($this->vend(['citybox_status_json' => []]));
        $this->assertSame('Unknown', $s->opsLabel());
    }

    public function test_not_yet_polled_and_unlinked_summaries(): void
    {
        $this->assertSame('Linked, not yet polled', ChillerStatus::forVend($this->vend(['citybox_synced_at' => null]))->summary());
        $this->assertSame('Not linked to a CityBox device', ChillerStatus::forVend($this->vend(['citybox_equipment_id' => null, 'citybox_synced_at' => null]))->summary());
    }

    public function test_session_state_poll_health_and_last_open_are_exposed(): void
    {
        $s = ChillerStatus::forVend($this->vend(['citybox_status_json' => [
            'equipment_status' => 1, 'device_state' => 'opening', 'device_state_at' => '2026-09-02 10:00:00',
            'poll' => ['at' => '2026-09-02 10:00:00', 'ok' => false, 'error' => '此设备没有商品', 'duration_ms' => 310, 'products_seen' => 0, 'total_qty' => 0],
            'last_ops_open' => ['at' => '2026-09-01 14:03:00', 'user_id' => 7, 'source' => 'vend_settings', 'msg_id' => 'm1'],
        ]]));

        $this->assertSame(DeviceState::Opening, $s->deviceState);
        $this->assertFalse($s->canOpenDoor()); // door already open ⇒ not FREE
        $a = $s->toArray();
        $this->assertSame('Door open', $a['device_state_label']);
        $this->assertFalse($a['poll']['ok']);
        $this->assertSame('vend_settings', $a['last_ops_open']['source']);

        $free = ChillerStatus::forVend($this->vend(['citybox_status_json' => ['device_state' => 'FREE']]));
        $this->assertTrue($free->canOpenDoor());
        $this->assertFalse(ChillerStatus::forVend($this->vend(['is_online' => 0, 'citybox_status_json' => ['device_state' => 'FREE']]))->canOpenDoor());
        $this->assertNull(ChillerStatus::forVend($this->vend())->deviceState);
    }

    public function test_vend_accessor_is_null_for_other_machine_kinds(): void
    {
        $this->assertNull($this->vend(['machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE])->chillerStatus());
        $this->assertInstanceOf(ChillerStatus::class, $this->vend()->chillerStatus());
    }
}
