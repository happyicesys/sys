<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Jobs\Vend\UpdateApkVersion;
use App\Models\Vend;
use App\Services\SubscribeMqttService;
use App\Services\VendDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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
 *
 * Also here, since they share the frame helper (audit 2026-09-16): the gate's
 * apk_ver_json read is cached and PWRON refreshes it (M3-18); a malformed frame
 * segment is skipped and a failing frame is logged, not lost (M3-08).
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
        $this->receiveMqttFrame($vendCode, ['Type' => 'P']);
    }

    private function receiveMqttFrame(int $vendCode, array $payload): void
    {
        $service = new VendDataService;
        $message = self::frame($vendCode, $payload);
        $std = $service->standardizedVendData($message, 'mqtt');
        $decoded = $service->decodeVendData($std);
        $service->processVendData($std, $decoded, '143.198.221.235', 'mqtt');
    }

    private static function frame(int $vendCode, array $payload): string
    {
        return 'f=4&t=5&m='.$vendCode.'&g=20&p='.base64_encode(json_encode($payload));
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

    public function test_the_version_read_behind_the_gate_is_cached_and_pwron_refreshes_it(): void
    {
        $vend = Vend::forceCreate(['code' => 2400]);

        $this->receiveMqttPoll(2400);
        Queue::assertNotPushed(PublishMqtt::class);

        // A version written behind the cache's back is not seen for 5 min…
        Vend::where('id', $vend->id)->update(['apk_ver_json' => json_encode(['apkver' => '305', 'deviceType' => 'ZC-328'])]);
        $this->receiveMqttPoll(2400);
        Queue::assertNotPushed(PublishMqtt::class);

        // …but PWRON forgets it before queueing UpdateApkVersion, so the gate re-reads at once.
        $this->receiveMqttFrame(2400, ['Type' => 'PWRON', 'apkver' => '305', 'deviceType' => 'ZC-328']);
        Queue::assertPushed(UpdateApkVersion::class, 1);
        $this->assertAcked(2400);
    }

    public function test_update_apk_version_forgets_the_cached_read_after_writing(): void
    {
        $vend = Vend::forceCreate(['code' => 2500]);
        $this->receiveMqttPoll(2500); // primes the cache with "no version"
        Queue::assertNotPushed(PublishMqtt::class);

        (new UpdateApkVersion(['apkver' => '13', 'deviceType' => 'ZC-83A'], $vend))->handle();

        $this->receiveMqttPoll(2500);
        $this->assertAcked(2500);
    }

    public function test_a_malformed_segment_is_skipped_and_a_value_containing_equals_survives(): void
    {
        $std = (new VendDataService)->standardizedVendData('f=4&t=5&m=2031&broken&=&p=abc==', 'mqtt');

        $this->assertSame(['f' => '4', 't' => '5', 'm' => '2031', '' => '', 'p' => 'abc=='], $std->all());
    }

    public function test_a_frame_that_throws_is_logged_with_context_and_the_subscriber_goes_on(): void
    {
        Vend::forceCreate(['code' => 2638, 'apk_ver_json' => ['apkver' => '13', 'deviceType' => 'ZC-83A']]);
        $this->partialMock(VendDataService::class, function ($mock) {
            $mock->shouldReceive('processVendData')->twice()->andThrow(new \RuntimeException('Connection refused [tcp://127.0.0.1:6379]'));
        });
        Log::spy();

        $subscriber = new SubscribeMqttService;
        $process = fn (string $message, string $topic) => $this->processData($message, SubscribeMqttService::IP_ADDRESS, SubscribeMqttService::CONNECTION_TYPE, $topic);
        $message = self::frame(2638, ['Type' => 'P']);

        $process->call($subscriber, $message, 'CS2638');
        $process->call($subscriber, $message, 'CS2638'); // the loop went on: a second frame is processed

        Log::shouldHaveReceived('error')->twice()->withArgs(function ($text, $context) use ($message) {
            return $text === 'MQTT frame processing failed'
                && $context['topic'] === 'CS2638'
                && $context['vend_code'] === '2638'
                && $context['frame_type'] === 'P'
                && $context['exception'] === \RuntimeException::class
                && str_contains($context['message'], 'Connection refused')
                && $context['raw'] === $message;
        });
    }
}
