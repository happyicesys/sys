<?php

namespace Tests\Feature;

use App\Http\Controllers\VendController;
use App\Jobs\Vend\RecordVendLinkHealth;
use App\Models\Vend;
use App\Services\VendDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * MQTT link health on the "P" heartbeat (big 306+ / small v14+).
 *
 * 306 / v14 recycle the MQTT client in-process on an MQTT-only outage instead
 * of rebooting the board. The reboot used to be counted in
 * vends.offline_restart_count; these metrics are what keeps the outage visible.
 * Pinned here:
 *   - a heartbeat that carries the counters is stored, one that does not is
 *     not (older builds must read as "no data", never "0 min offline");
 *   - identical totals are written once, not on every 30 s small-board poll;
 *   - the stored value is the day's MAX, so a stale heartbeat cannot lower it;
 *   - the Ops Dashboard fields stay NULL when a machine sent nothing.
 */
class VendLinkHealthTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-25 11:00:00');
        $this->vend = Vend::forceCreate(['code' => 2031]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function heartbeat(array $extra = []): void
    {
        $service = new VendDataService;
        $input = [
            'f' => '120', 't' => '5', 'm' => '2031', 'g' => '20',
            'p' => base64_encode(json_encode(array_merge(['Type' => 'P'], $extra))),
        ];
        $std = $service->standardizedVendData($input, 'http');
        $service->processVendData($std, $service->decodeVendData($std), '10.0.0.1', 'http');
    }

    private function linkFields(array $overrides = []): array
    {
        return array_merge([
            'MqttDay' => '2026-09-25',
            'MqttDrops' => 3,
            'MqttConnFails' => 8,
            'MqttRecycles' => 1,
            'MqttOfflineSec' => 425,
            'MqttUp' => 1,
        ], $overrides);
    }

    public function test_a_heartbeat_with_link_health_is_recorded_for_the_devices_day(): void
    {
        Queue::fake();

        $this->heartbeat($this->linkFields());

        Queue::assertPushedOn('low', RecordVendLinkHealth::class, fn (RecordVendLinkHealth $job) => $job->vendId === $this->vend->id
            && $job->vendCode === '2031'
            && $job->date === '2026-09-25'
            && $job->values === ['mqtt_drops' => 3, 'mqtt_conn_fails' => 8, 'mqtt_recycles' => 1, 'mqtt_offline_s' => 425]);
    }

    public function test_an_older_build_that_sends_no_link_health_writes_nothing(): void
    {
        Queue::fake();

        $this->heartbeat(['OfflineRestartCount' => 0, 'OfflineRestartCountDatetime' => '2026-09-25 10:00:00']);

        Queue::assertNotPushed(RecordVendLinkHealth::class);
    }

    public function test_identical_totals_are_written_once_and_an_outage_at_most_once_a_minute(): void
    {
        Queue::fake();

        $this->heartbeat($this->linkFields());
        $this->heartbeat($this->linkFields());                            // same totals
        $this->heartbeat($this->linkFields(['MqttOfflineSec' => 470]));   // same minute (7)
        Queue::assertPushed(RecordVendLinkHealth::class, 1);

        $this->heartbeat($this->linkFields(['MqttOfflineSec' => 481]));   // minute 8
        $this->heartbeat($this->linkFields(['MqttDrops' => 4, 'MqttOfflineSec' => 481])); // a new drop
        Queue::assertPushed(RecordVendLinkHealth::class, 3);
    }

    public function test_a_device_day_more_than_a_day_off_falls_back_to_ours(): void
    {
        Queue::fake();

        $this->heartbeat($this->linkFields(['MqttDay' => '2019-01-01']));
        Queue::assertPushed(RecordVendLinkHealth::class, fn ($job) => $job->date === '2026-09-25');

        // A device a few minutes behind at midnight is still believed.
        $this->heartbeat($this->linkFields(['MqttDay' => '2026-09-24', 'MqttDrops' => 9]));
        Queue::assertPushed(RecordVendLinkHealth::class, fn ($job) => $job->date === '2026-09-24');
    }

    public function test_the_stored_value_is_the_days_maximum(): void
    {
        $values = ['mqtt_drops' => 3, 'mqtt_conn_fails' => 8, 'mqtt_recycles' => 1, 'mqtt_offline_s' => 425];
        (new RecordVendLinkHealth($this->vend->id, '2031', '2026-09-25', $values))->handle();

        // A late / duplicated heartbeat with lower totals must not lower anything.
        (new RecordVendLinkHealth($this->vend->id, '2031', '2026-09-25', ['mqtt_drops' => 1, 'mqtt_offline_s' => 60]))->handle();
        // A later one raises.
        (new RecordVendLinkHealth($this->vend->id, '2031', '2026-09-25', ['mqtt_offline_s' => 900, 'not_a_metric' => 5]))->handle();

        $rows = DB::table('vend_daily_stats')->where('vend_id', $this->vend->id)->pluck('count', 'metric')->all();
        $this->assertSame(['mqtt_conn_fails' => 8, 'mqtt_drops' => 3, 'mqtt_offline_s' => 900, 'mqtt_recycles' => 1], collect($rows)->sortKeys()->all());
    }

    public function test_dashboard_fields_are_null_when_a_machine_sent_nothing(): void
    {
        $fields = (new \ReflectionMethod(VendController::class, 'linkHealthFields'));
        $fields->setAccessible(true);

        $none = $fields->invoke(null, [], '2026-09-25', '2026-09-24', '2026-09-23');
        $this->assertSame([
            'mqtt_offline_1d_s' => null, 'mqtt_offline_2d_s' => null, 'mqtt_offline_3d_s' => null,
            'mqtt_drops_1d' => null, 'mqtt_recycles_1d' => null, 'mqtt_conn_fails_1d' => null,
        ], $none);

        $some = $fields->invoke(null, [
            'mqtt_offline_s' => ['2026-09-25' => 0, '2026-09-23' => 3600],
            'mqtt_drops' => ['2026-09-25' => 0],
        ], '2026-09-25', '2026-09-24', '2026-09-23');
        $this->assertSame(0, $some['mqtt_offline_1d_s']);   // a real zero stays zero
        $this->assertNull($some['mqtt_offline_2d_s']);      // a silent day stays null
        $this->assertSame(3600, $some['mqtt_offline_3d_s']);
        $this->assertSame(0, $some['mqtt_drops_1d']);
    }
}
