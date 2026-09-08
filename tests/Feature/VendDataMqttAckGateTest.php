<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Models\Vend;
use App\Services\VendDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Which APKs get the "1" ack back over MQTT.
 *
 * The APK's offline-reboot guard resets its "MQTT offline" timer ONLY when this
 * ack arrives, so a machine that is denied it reboots itself every 10 minutes
 * while perfectly connected. The gate used to be a bare `apkver >= 129`, which
 * was fine until the small-board series restarted at versionCode 11
 * (2026-09-05): vend 2638 on v12 rebooted every 11 minutes for a day. These
 * tests pin the rule: numeric floor for the touchscreen streams, device type
 * for the restarted small-board series, and still nothing for the legacy OEM
 * builds.
 */
class VendDataMqttAckGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** Feed one MQTT frame (the APK's query-string form) through the service. */
    private function receiveMqttPoll(int $vendCode): void
    {
        $service = new VendDataService;
        $message = 'f=4&t=5&m='.$vendCode.'&g=20&p='.base64_encode(json_encode(['Type' => 'P']));
        $std = $service->standardizedVendData($message, 'mqtt');
        $decoded = $service->decodeVendData($std);
        $service->processVendData($std, $decoded, '143.198.221.235', 'mqtt');
    }

    private function assertAcked(int $vendCode): void
    {
        Queue::assertPushed(PublishMqtt::class, function (PublishMqtt $job) use ($vendCode) {
            // topic/message are protected on the job; read them in its scope.
            [$topic, $message] = (fn () => [$this->topic, $this->message])->call($job);

            return $topic === 'CM'.$vendCode && $message === '4,4,MQ==';
        });
    }

    public function test_small_board_new_series_gets_the_ack(): void
    {
        Vend::forceCreate(['code' => 2638, 'apk_ver_json' => ['apkver' => '12', 'deviceType' => 'ZC-83A']]);

        $this->receiveMqttPoll(2638);

        $this->assertAcked(2638);
    }

    public function test_touchscreen_stream_still_gets_the_ack(): void
    {
        Vend::forceCreate(['code' => 2100, 'apk_ver_json' => ['apkver' => '303', 'deviceType' => 'ZC-328']]);

        $this->receiveMqttPoll(2100);

        $this->assertAcked(2100);
    }

    public function test_legacy_oem_build_is_still_not_acked(): void
    {
        Vend::forceCreate(['code' => 2200, 'apk_ver_json' => ['apkver' => '108', 'deviceType' => 'ANDROID']]);

        $this->receiveMqttPoll(2200);

        Queue::assertNotPushed(PublishMqtt::class);
    }

    public function test_machine_that_never_reported_a_version_is_not_acked(): void
    {
        Vend::forceCreate(['code' => 2300]);

        $this->receiveMqttPoll(2300);

        Queue::assertNotPushed(PublishMqtt::class);
    }
}
