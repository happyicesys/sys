<?php

namespace Tests\Unit;

use App\Models\VendTemp;
use App\Support\MachineStatusSummary;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * The Needs attention list shows each machine's live status (Brian, 2026-10-18) with the grid's own
 * Machine Status rules and colours.
 */
class MachineStatusSummaryTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-10-18 12:00:00');
    }

    private function row(array $overrides = []): object
    {
        return (object) array_merge([
            'machine_type' => 'vending_machine',
            'is_online' => 1, 'last_updated_at' => '2026-10-18 11:55:00',
            'is_mqtt' => 1, 'is_mqtt_active' => 1, 'mqtt_last_updated_at' => '2026-10-18 11:59:00',
            'modem_unit_is_online' => null, 'modem_unit_last_updated_at' => null,
            'temp' => -185, 'temp_updated_at' => '2026-10-18 11:50:00', 'is_temp_error' => 0,
            'parameter_json' => json_encode(['t2' => -172, 'door' => 'close', 'Sensor' => 1]),
        ], $overrides);
    }

    /** @return array<string, array{value: string, tone: string}> */
    private function badges(object $row): array
    {
        return collect(MachineStatusSummary::forRow($row, $this->now))->keyBy('label')
            ->map(fn ($b) => ['value' => $b['value'], 'tone' => $b['tone']])->all();
    }

    public function test_a_healthy_vending_machine(): void
    {
        $badges = $this->badges($this->row());

        $this->assertSame(['HTTP', 'MQTT', 'Temp', 'Door', 'Drop sensor'], array_keys($badges));
        $this->assertSame(['value' => 'Online · 5m', 'tone' => 'ok'], $badges['HTTP']);
        $this->assertSame(['value' => 'T1 -18.5°C · T2 -17.2°C · 10m', 'tone' => 'ok'], $badges['Temp']);
        $this->assertSame('ok', $badges['Door']['tone']);
        $this->assertSame(['value' => 'Enabled', 'tone' => 'ok'], $badges['Drop sensor']);
    }

    public function test_faults_turn_red(): void
    {
        $badges = $this->badges($this->row([
            'is_online' => 0, 'last_updated_at' => '2026-10-18 07:24:00',
            'is_mqtt_active' => 0,
            'modem_unit_is_online' => 0, 'modem_unit_last_updated_at' => '2026-10-16 12:00:00',
            'temp' => -120,
            'parameter_json' => ['t2' => VendTemp::TEMPERATURE_ERROR, 'door' => 'open', 'Sensor' => 2],
        ]));

        $this->assertSame(['value' => 'Offline · 4.6h', 'tone' => 'bad'], $badges['HTTP']);
        $this->assertSame('idle', $badges['MQTT']['tone'], 'the grid greys an offline MQTT, it does not red it');
        $this->assertSame(['value' => 'Offline · 2d', 'tone' => 'bad'], $badges['Modem']);
        $this->assertSame(['value' => 'T1 -12.0°C · 10m', 'tone' => 'bad'], $badges['Temp'], 'a T2 error is left out');
        $this->assertSame(['value' => 'Open', 'tone' => 'bad'], $badges['Door']);
        $this->assertSame(['value' => 'Disabled', 'tone' => 'bad'], $badges['Drop sensor']);
    }

    public function test_a_freezer_shows_its_links_only(): void
    {
        $badges = $this->badges($this->row(['machine_type' => 'smart_freezer', 'is_mqtt' => 0]));

        $this->assertSame(['HTTP'], array_keys($badges));
    }
}
