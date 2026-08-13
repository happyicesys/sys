<?php

namespace Tests\Feature;

use App\Jobs\Vend\PushApkSettingSync;
use App\Models\ApkSetting;
use App\Models\Vend;
use App\Services\VendJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Auto-push on /apk-settings changes.
 *
 * Before this, only the manual "Push" button told a machine to re-read its
 * settings — saving, binding a vend, or changing media reflected on the fleet
 * only after the next reboot. These tests pin the two properties that matter:
 * a change schedules exactly one push, and a burst of changes collapses into
 * one push wave (a multi-file upload is one request per file, and the largest
 * setting row binds 84 machines).
 */
class ApkSettingAutoPushTest extends TestCase
{
    use RefreshDatabase;

    private function makeSetting(): ApkSetting
    {
        return ApkSetting::create([
            'name' => 'Test Setting',
            'settings_parameter_json' => [],
        ]);
    }

    /** No VendFactory exists; `code` is the only required column. */
    private function makeVend(int $code): Vend
    {
        return Vend::forceCreate(['code' => $code]);
    }

    public function test_schedule_queues_one_push_per_debounce_window(): void
    {
        Queue::fake();
        $setting = $this->makeSetting();

        // A five-file dropzone upload = five requests in quick succession.
        for ($i = 0; $i < 5; $i++) {
            PushApkSettingSync::schedule($setting->id);
        }

        Queue::assertPushed(PushApkSettingSync::class, 1);
    }

    public function test_a_later_change_schedules_a_new_push_once_the_guard_clears(): void
    {
        Queue::fake();
        $setting = $this->makeSetting();

        PushApkSettingSync::schedule($setting->id);
        // The job clears the guard when it runs; simulate that.
        Cache::forget(PushApkSettingSync::cacheKey($setting->id));
        PushApkSettingSync::schedule($setting->id);

        Queue::assertPushed(PushApkSettingSync::class, 2);
    }

    public function test_two_settings_do_not_share_a_debounce_guard(): void
    {
        Queue::fake();
        $a = $this->makeSetting();
        $b = $this->makeSetting();

        PushApkSettingSync::schedule($a->id);
        PushApkSettingSync::schedule($b->id);

        Queue::assertPushed(PushApkSettingSync::class, 2);
    }

    public function test_schedule_ignores_a_null_setting_id(): void
    {
        Queue::fake();

        PushApkSettingSync::schedule(null);

        Queue::assertNothingPushed();
    }

    public function test_job_pushes_to_every_bound_vend(): void
    {
        $setting = $this->makeSetting();
        $vendA = $this->makeVend(9001);
        $vendB = $this->makeVend(9002);
        $setting->vends()->sync([$vendA->id, $vendB->id]);

        $pushed = [];
        $service = $this->createMock(VendJobService::class);
        $service->method('syncSettingsToVend')
            ->willReturnCallback(function ($vend) use (&$pushed) {
                $pushed[] = (int) $vend->code;

                return null;
            });

        (new PushApkSettingSync($setting->id))->handle($service);

        sort($pushed);
        $this->assertSame([9001, 9002], $pushed);
    }

    /**
     * update() computes the machines a save UNBINDS by diffing the bound set
     * either side of sync(), because the debounced fan-out only reaches what
     * stays bound. This pins the pluck/diff those pushes depend on.
     */
    public function test_bound_set_diff_identifies_unbound_machines(): void
    {
        $setting = $this->makeSetting();
        $keep = $this->makeVend(9101);
        $drop = $this->makeVend(9102);
        $add = $this->makeVend(9103);
        $setting->vends()->sync([$keep->id, $drop->id]);

        $before = $setting->vends()->pluck('vends.id')->all();
        $setting->vends()->sync([$keep->id, $add->id]);
        $after = $setting->vends()->pluck('vends.id')->all();

        $this->assertEqualsCanonicalizing([$keep->id, $drop->id], $before);
        $this->assertEqualsCanonicalizing([$keep->id, $add->id], $after);
        $this->assertSame(
            [$drop->id],
            array_values(array_diff($before, $after)),
            'only the unbound machine needs its own push'
        );
    }

    public function test_job_releases_the_guard_so_the_next_change_can_schedule(): void
    {
        $setting = $this->makeSetting();
        Cache::put(PushApkSettingSync::cacheKey($setting->id), true, 60);

        $service = $this->createMock(VendJobService::class);
        (new PushApkSettingSync($setting->id))->handle($service);

        $this->assertFalse(Cache::has(PushApkSettingSync::cacheKey($setting->id)));
    }

    public function test_job_is_a_no_op_when_the_setting_was_deleted(): void
    {
        $setting = $this->makeSetting();
        $id = $setting->id;
        $setting->delete();

        $service = $this->createMock(VendJobService::class);
        $service->expects($this->never())->method('syncSettingsToVend');

        (new PushApkSettingSync($id))->handle($service);
    }

    public function test_one_failing_vend_does_not_stop_the_rest_of_the_fleet(): void
    {
        $setting = $this->makeSetting();
        $vendA = $this->makeVend(9003);
        $vendB = $this->makeVend(9004);
        $setting->vends()->sync([$vendA->id, $vendB->id]);

        $seen = [];
        $service = $this->createMock(VendJobService::class);
        $service->method('syncSettingsToVend')
            ->willReturnCallback(function ($vend) use (&$seen) {
                $seen[] = (int) $vend->code;
                if ((int) $vend->code === 9003) {
                    throw new \RuntimeException('broker down');
                }

                return null;
            });

        (new PushApkSettingSync($setting->id))->handle($service);

        $this->assertCount(2, $seen, 'the second machine must still be attempted');
    }
}
