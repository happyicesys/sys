<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Models\ApkRelease;
use App\Models\User;
use App\Models\Vend;
use App\Services\OtaCheckNudge;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * ota:nudge-stale — the overnight OTA_CHECK to machines behind the latest build.
 *
 * The contract: only machines that can act on the nudge and still need it get one.
 * Anything already on the latest build, too old to have an OTA client, offline,
 * retired, or on another channel's board family gets nothing.
 */
class OtaNudgeStaleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function publish(int $versionCode, string $status = ApkRelease::STATUS_PUBLISHED, string $channel = 'vending'): ApkRelease
    {
        return ApkRelease::create([
            'channel' => $channel,
            'package_name' => $channel === 'vending_small' ? 'com.venderroute.small' : 'com.venderroute',
            'version_code' => $versionCode,
            'version_name' => (string) $versionCode,
            'file_url' => "https://example.test/{$versionCode}.apk",
            'file_path' => "sys/vends/apk/{$versionCode}.apk",
            'sha256' => str_repeat('a', 64),
            'size_bytes' => 1,
            'rollout_permille' => 1000,
            'status' => $status,
        ]);
    }

    /**
     * An online, active vending machine. `apk_version_code` is guarded (production
     * writes it through OtaController::recordCheckIn's forceFill), so every attribute
     * goes through forceFill here.
     */
    private function vend(int $code, array $attrs = []): Vend
    {
        $vend = new Vend;
        $vend->forceFill(array_merge([
            'code' => $code,
            'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'is_active' => true,
            'is_disposed' => false,
            'is_online' => true,
            'operator_id' => 1,
        ], $attrs))->save();

        return $vend->refresh();
    }

    private function pwron(string $apkver, string $deviceType = 'ZC-328'): array
    {
        return ['apk_ver_json' => ['vid' => 1, 'Type' => 'PWRON', 'apkver' => $apkver, 'deviceType' => $deviceType]];
    }

    /** Vend codes of every queued OTA_CHECK, from each job's MQTT topic. */
    private function nudgedCodes(): array
    {
        $topic = new \ReflectionProperty(PublishMqtt::class, 'topic');

        return Queue::pushed(PublishMqtt::class)
            ->map(fn ($job) => (int) substr($topic->getValue($job), 2))
            ->sort()->values()->all();
    }

    public function test_it_nudges_only_online_ota_capable_machines_behind_the_latest_build(): void
    {
        Queue::fake();
        $this->publish(303);
        $this->publish(305);

        $this->vend(2206, $this->pwron('301'));                                  // stuck on 301
        $this->vend(2741, $this->pwron('303') + ['apk_version_code' => 301]);    // stuck on 303, stale column
        $this->vend(1149, $this->pwron('301', 'INPAD3101') + ['apk_version_code' => 301]);

        $this->vend(2046, $this->pwron('305'));                                  // already latest
        $this->vend(2296, $this->pwron('305') + ['apk_version_code' => 301]);    // latest, stale column
        $this->vend(2100, $this->pwron('220'));                                  // no OTA client
        $this->vend(2101);                                                        // never reported
        $this->vend(2102, $this->pwron('301') + ['is_online' => false]);
        $this->vend(2103, $this->pwron('301') + ['is_active' => false]);
        $this->vend(2104, $this->pwron('301') + ['is_disposed' => true]);
        $this->vend(4608, $this->pwron('129', 'ZC-83A') + ['apk_version_code' => 303]); // small board

        $this->artisan('ota:nudge-stale')
            ->expectsOutputToContain('[vending] latest 305; nudged 3 machine(s)')
            ->assertSuccessful();

        $this->assertSame([1149, 2206, 2741], $this->nudgedCodes());
    }

    public function test_a_draft_build_does_not_count_as_latest(): void
    {
        Queue::fake();
        $this->publish(305);
        $this->publish(306, ApkRelease::STATUS_DRAFT);
        $this->vend(2046, $this->pwron('305'));

        $this->artisan('ota:nudge-stale')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_nothing_is_sent_when_the_channel_has_no_published_build(): void
    {
        Queue::fake();
        $this->publish(305, ApkRelease::STATUS_DRAFT);
        $this->vend(2206, $this->pwron('301'));

        $this->artisan('ota:nudge-stale')
            ->expectsOutputToContain('no published build')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_dry_run_lists_targets_without_publishing(): void
    {
        Queue::fake();
        $this->publish(305);
        $this->vend(2206, $this->pwron('301'));

        $this->artisan('ota:nudge-stale --dry-run')
            ->expectsOutputToContain('would nudge 1 machine(s): 2206:301')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_the_off_switch_stops_the_nudge(): void
    {
        Queue::fake();
        config(['ota.nightly_nudge.enabled' => false]);
        $this->publish(305);
        $this->vend(2206, $this->pwron('301'));

        $this->artisan('ota:nudge-stale')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_the_frame_is_a_signed_ota_check_for_that_machine(): void
    {
        Carbon::setTestNow('2026-09-16 01:00:00');
        $vend = $this->vend(2206, ['private_key' => 'SECRETKEY0000001']);

        [$fid, $length, $content, $md5] = explode(',', app(OtaCheckNudge::class)->frame($vend));

        $this->assertSame('1', $fid);
        $this->assertSame(strlen($content), (int) $length);
        $this->assertSame(md5("1,{$length},{$content}SECRETKEY0000001"), $md5);
        $this->assertSame(
            ['Type' => 'OTA_CHECK', 'time' => Carbon::now()->timestamp, 'action' => '', 'mid' => 2206],
            json_decode(base64_decode($content), true)
        );
    }

    /**
     * schedule:run boots once a minute, and between() captures "now" at that boot —
     * so each check rebuilds the schedule at the simulated moment, a few seconds past
     * the minute as in production. A window ending exactly at 07:00 fails this.
     */
    public function test_it_is_scheduled_every_thirty_minutes_from_one_to_seven_am(): void
    {
        $due = function (string $time) {
            Carbon::setTestNow(Carbon::parse("2026-09-16 {$time}:03", config('app.timezone')));

            $schedule = new Schedule(config('app.timezone'));
            $build = new \ReflectionMethod(app(\Illuminate\Contracts\Console\Kernel::class), 'schedule');
            $build->invoke(app(\Illuminate\Contracts\Console\Kernel::class), $schedule);

            $event = collect($schedule->events())
                ->first(fn ($e) => str_contains((string) $e->command, 'ota:nudge-stale'));
            $this->assertNotNull($event, 'ota:nudge-stale is not scheduled');

            return $event->isDue(app()) && $event->filtersPass(app());
        };

        $runs = collect(range(0, 23 * 60 + 59))
            ->map(fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60))
            ->filter($due)
            ->values()
            ->all();

        $this->assertSame(
            ['01:00', '01:30', '02:00', '02:30', '03:00', '03:30', '04:00',
                '04:30', '05:00', '05:30', '06:00', '06:30', '07:00'],
            $runs
        );
    }

    public function test_the_push_ota_check_button_still_nudges_the_whole_channel(): void
    {
        Queue::fake();
        Permission::findOrCreate('update apk-releases', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('update apk-releases');

        $this->vend(2046, $this->pwron('305'));
        $this->vend(2206, $this->pwron('301'));

        $this->actingAs($user)
            ->post('/apk-releases/push-ota-check', ['channel' => 'vending'])
            ->assertRedirect();

        $this->assertSame([2046, 2206], $this->nudgedCodes());
    }
}
