<?php

namespace Tests\Feature;

use App\Http\Controllers\FreezerControlController;
use App\Jobs\PublishMqtt;
use App\Models\FreezerControlCommand;
use App\Models\FreezerSetpointSchedule;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;
use App\Services\Freezer\FreezerStatusService;
use App\Support\OperatorScope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * APK 14 additions on the mark1 side (2026-09-17): the `beep` op, the daily setpoint schedule and
 * its runner, the thermostat alarm bits in FREEZERSTATUS, and the Ops Dashboard health column.
 */
class FreezerV14AdditionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'code' => 'HIPL', 'name' => 'HIPL',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->user = User::factory()->create(['name' => 'Tech One', 'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID]);
        foreach (['read machine-settings', 'update machine-settings', 'read vend-customers', 'admin-access vend-customers'] as $p) {
            $this->user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        OperatorScope::flush();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        OperatorScope::flush();
        parent::tearDown();
    }

    private function freezer(array $attrs = []): Vend
    {
        $attrs = array_merge([
            'code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => 1, 'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'vend_model_id' => 1,
            'apk_version_code' => 14, 'private_key' => 'TESTKEY000000001',
        ], $attrs);
        $vend = new Vend;
        $vend->forceFill($attrs)->save();

        return $vend->refresh();
    }

    private function sentArgs(): array
    {
        $args = null;
        Queue::assertPushed(PublishMqtt::class, function (PublishMqtt $job) use (&$args) {
            [, $wire] = (fn () => [$this->topic, $this->message])->call($job);
            $args = json_decode(base64_decode(explode(',', $wire)[2]), true)['args'];

            return true;
        });

        return $args;
    }

    // ------------------------------------------------------------------ beep

    public function test_beep_defaults_to_three_seconds_and_is_bounded(): void
    {
        $vend = $this->freezer();
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'beep'])->assertStatus(202);
        $this->assertSame(['seconds' => 3], $this->sentArgs());

        FreezerControlCommand::query()->delete();
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'beep', 'args' => ['seconds' => 11]])
            ->assertStatus(422)->assertJsonValidationErrors('args.seconds');
    }

    public function test_beep_needs_app_14(): void
    {
        $vend = $this->freezer(['apk_version_code' => 13]);
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'beep'])->assertStatus(422);
        Queue::assertNotPushed(PublishMqtt::class);
        $this->assertSame(10, $this->getJson("/vends/{$vend->id}/freezer-controls")->json('beep_seconds_max'));
    }

    // -------------------------------------------------------------- schedule

    public function test_schedule_entries_are_added_listed_paused_and_removed(): void
    {
        Carbon::setTestNow('2026-09-17 10:00:00');
        $vend = $this->freezer();

        $this->postJson("/vends/{$vend->id}/freezer-controls/schedules", ['run_at' => '22:00', 'celsius' => -16])->assertCreated();
        $this->postJson("/vends/{$vend->id}/freezer-controls/schedules", ['run_at' => '22:00', 'celsius' => -18])
            ->assertStatus(422)->assertJsonValidationErrors('run_at');
        $this->postJson("/vends/{$vend->id}/freezer-controls/schedules", ['run_at' => '06:00', 'celsius' => -40])
            ->assertStatus(422)->assertJsonValidationErrors('celsius');
        // Added after its time today: waits for tomorrow instead of firing now.
        $this->postJson("/vends/{$vend->id}/freezer-controls/schedules", ['run_at' => '06:00', 'celsius' => -22])->assertCreated();

        $schedule = $this->getJson("/vends/{$vend->id}/freezer-controls")->json('schedule');
        $this->assertSame(['06:00', '22:00'], array_column($schedule, 'run_at'));
        $this->assertSame('2026-09-17', $schedule[0]['last_run_on']);
        $this->assertNull($schedule[1]['last_run_on']);
        $this->assertSame('Tech One', $schedule[1]['created_by']);

        $id = $schedule[1]['id'];
        $this->patchJson("/vends/{$vend->id}/freezer-controls/schedules/{$id}", ['is_active' => false])->assertOk();
        $this->assertFalse(FreezerSetpointSchedule::find($id)->is_active);
        $this->deleteJson("/vends/{$vend->id}/freezer-controls/schedules/{$id}")->assertOk();
        $this->assertNull(FreezerSetpointSchedule::find($id));

        $other = $this->freezer(['code' => 50002]);
        $this->deleteJson("/vends/{$other->id}/freezer-controls/schedules/{$schedule[0]['id']}")->assertNotFound();
    }

    public function test_schedule_needs_update_permission(): void
    {
        $vend = $this->freezer();
        $viewer = User::factory()->create(['operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID]);
        $viewer->givePermissionTo(Permission::findOrCreate('read machine-settings', 'web'));
        $this->actingAs($viewer)
            ->postJson("/vends/{$vend->id}/freezer-controls/schedules", ['run_at' => '22:00', 'celsius' => -16])
            ->assertForbidden();
    }

    private function entry(Vend $vend, string $runAt, int $celsius, array $attrs = []): FreezerSetpointSchedule
    {
        return FreezerSetpointSchedule::create(array_merge([
            'vend_id' => $vend->id, 'run_at' => $runAt, 'celsius' => $celsius,
        ], $attrs));
    }

    public function test_runner_sends_the_latest_due_entry_once_as_a_scheduled_setpoint(): void
    {
        $vend = $this->freezer();
        $early = $this->entry($vend, '22:00:00', -16);
        $late = $this->entry($vend, '22:05:00', -17);
        $tomorrow = $this->entry($vend, '23:00:00', -20);

        Carbon::setTestNow('2026-09-17 22:06:00');
        $this->artisan('freezer:run-setpoint-schedules')->assertSuccessful();

        $command = FreezerControlCommand::sole();
        $this->assertSame('setpoint', $command->op);
        $this->assertSame(['celsius' => -17], $command->args);
        $this->assertSame(FreezerControlCommand::SOURCE_SCHEDULE, $command->source);
        $this->assertSame('Schedule 22:05', $command->requested_by_name);
        $this->assertNull($command->requested_by);
        $this->assertSame('2026-09-17', $late->fresh()->last_run_on->toDateString());
        $this->assertSame($command->id, $late->fresh()->last_command_id);
        $this->assertSame('2026-09-17', $early->fresh()->last_run_on->toDateString());
        $this->assertNull($tomorrow->fresh()->last_run_on);

        // Not again today, even after the command was answered.
        $command->update(['status' => 'ok', 'responded_at' => now()]);
        Carbon::setTestNow('2026-09-17 22:07:00');
        $this->artisan('freezer:run-setpoint-schedules')->assertSuccessful();
        $this->assertSame(1, FreezerControlCommand::count());

        // Next day it runs again.
        Carbon::setTestNow('2026-09-18 22:01:00');
        $this->artisan('freezer:run-setpoint-schedules')->assertSuccessful();
        $this->assertSame(2, FreezerControlCommand::count());
        $this->assertSame(['celsius' => -16], FreezerControlCommand::latest('id')->first()->args);

        $show = $this->getJson("/vends/{$vend->id}/freezer-controls")->json('commands.0');
        $this->assertSame('schedule', $show['source']);
    }

    public function test_runner_waits_while_busy_and_skips_what_is_too_late(): void
    {
        $vend = $this->freezer();
        $entry = $this->entry($vend, '22:00:00', -16);
        FreezerControlCommand::create([
            'vend_id' => $vend->id, 'cmd_id' => 'PENDING1', 'op' => 'status', 'status' => 'pending',
            'expires_at' => Carbon::parse('2026-09-17 22:01:30'),
        ]);

        Carbon::setTestNow('2026-09-17 22:01:00');
        $this->artisan('freezer:run-setpoint-schedules')->assertSuccessful();
        $this->assertSame(1, FreezerControlCommand::count());
        $this->assertNull($entry->fresh()->last_run_on);

        Carbon::setTestNow('2026-09-17 22:30:00');
        $this->artisan('freezer:run-setpoint-schedules')->assertSuccessful();
        $this->assertSame(1, FreezerControlCommand::count());
        $this->assertSame('2026-09-17', $entry->fresh()->last_run_on->toDateString());
    }

    public function test_runner_ignores_paused_entries_and_old_apps(): void
    {
        $paused = $this->freezer();
        $this->entry($paused, '22:00:00', -16, ['is_active' => false]);
        $old = $this->freezer(['code' => 50002, 'apk_version_code' => 12]);
        $oldEntry = $this->entry($old, '22:00:00', -16);

        Carbon::setTestNow('2026-09-17 22:01:00');
        $this->artisan('freezer:run-setpoint-schedules')->assertSuccessful();
        $this->assertSame(0, FreezerControlCommand::count());
        $this->assertSame('2026-09-17', $oldEntry->fresh()->last_run_on->toDateString());
    }

    // ------------------------------------------------------------ camera photos

    public function test_the_panel_lists_the_last_five_camera_photos_newest_first(): void
    {
        $vend = $this->freezer();
        // Seven uploaded stills plus one photo command that never produced a file.
        foreach (range(1, 7) as $i) {
            FreezerControlCommand::create([
                'vend_id' => $vend->id, 'cmd_id' => 'PH'.$i, 'op' => 'photo', 'args' => ['cameraId' => $i % 3],
                'status' => 'ok', 'requested_by_name' => 'Tech One',
                'attachment_path' => 'freezer-photos/'.$vend->id.'/PH'.$i.'.jpg',
                'attachment_type' => FreezerControlCommand::ATTACHMENT_PHOTO,
                'responded_at' => Carbon::parse('2026-09-18 10:0'.$i.':00'),
            ]);
        }
        FreezerControlCommand::create([
            'vend_id' => $vend->id, 'cmd_id' => 'PHX', 'op' => 'photo', 'args' => ['cameraId' => 3],
            'status' => 'refused', 'response_msg' => 'camera_not_found: 3',
        ]);

        $photos = $this->getJson("/vends/{$vend->id}/freezer-controls")->json('photos');

        $this->assertCount(FreezerControlController::PHOTO_HISTORY, $photos);
        // Newest first, and only the ones that actually have an image.
        $this->assertSame(['PH7', 'PH6', 'PH5', 'PH4', 'PH3'], array_map(
            fn ($p) => FreezerControlCommand::find($p['id'])->cmd_id,
            $photos,
        ));
        $this->assertSame(1, $photos[0]['camera_id']);
        $this->assertSame('Tech One', $photos[0]['by']);
        $this->assertStringContainsString("/vends/{$vend->id}/freezer-controls/", $photos[0]['url']);
        $this->assertStringEndsWith('/attachment', $photos[0]['url']);
    }

    // ------------------------------------------------- alarms + dashboard

    public function test_status_keeps_the_thermostat_alarm_bits(): void
    {
        $vend = $this->freezer();
        app(FreezerStatusService::class)->syncStatus($vend, [
            'powerState' => 'connected',
            'alarms' => ['highTemp' => true, 'lowTemp' => false, 'sensorOk' => 'yes', 'communicating' => true, 'other' => 1],
        ]);
        $json = json_decode(DB::table('vends')->where('id', $vend->id)->value('freezer_status_json'), true);
        // MySQL stores object keys sorted; a non-boolean bit is dropped, not guessed.
        $this->assertEquals(['highTemp' => true, 'lowTemp' => false, 'communicating' => true], $json['status']['alarms']);

        // A packet without the block leaves the last bits alone.
        app(FreezerStatusService::class)->syncStatus($vend, ['powerState' => 'cut']);
        $json = json_decode(DB::table('vends')->where('id', $vend->id)->value('freezer_status_json'), true);
        $this->assertTrue($json['status']['alarms']['highTemp']);
        $this->assertSame('cut', $json['status']['powerState']);
    }

    public function test_ops_dashboard_carries_freezer_health_for_freezers_only(): void
    {
        foreach ([[50001, Vend::MACHINE_TYPE_SMART_FREEZER], [1001, 'vending_machine']] as [$code, $type]) {
            $customerId = DB::table('customers')->insertGetId([
                'name' => "Site {$code}", 'profile_id' => 1, 'status_id' => 1, 'is_active' => 1,
                'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('vends')->insert([
                'code' => $code, 'name' => "Machine {$code}", 'machine_type' => $type,
                'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'customer_id' => $customerId,
                'is_active' => 1, 'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
                'freezer_status_json' => json_encode([
                    'status' => ['powerState' => 'cut', 'locks' => [['doorId' => 1, 'lockOnlineState' => 'offline']],
                        'cameras' => [['id' => 3, 'state' => 'offline']], 'alarms' => ['highTemp' => true]],
                    'status_at' => '2026-09-17 09:50:00',
                    'selfcheck' => ['passed' => false, 'errors' => ['[错误] 开门按钮未工作'], 'msgs' => []],
                    'selfcheck_at' => '2026-09-17 09:00:00',
                ]),
            ]);
        }

        $rows = [];
        $this->get('/vends/customers?autoload=1')->assertOk()->assertInertia(function (Assert $page) use (&$rows) {
            foreach ($page->toArray()['props']['vends']['data'] as $vend) {
                $rows[(int) $vend['code']] = $vend['freezer_health'];
            }
        });

        $this->assertNull($rows[1001]);
        $health = $rows[50001];
        $this->assertSame('2026-09-17 09:50:00', $health['status_at']);
        $this->assertSame('cut', $health['power']);
        $this->assertSame('offline', $health['lock_link']);
        $this->assertSame([['id' => 3, 'state' => 'offline']], $health['cameras']);
        $this->assertSame(['highTemp' => true], $health['alarms']);
        $this->assertFalse($health['selfcheck_passed']);
        $this->assertSame(['[错误] 开门按钮未工作'], $health['selfcheck_errors']);
    }
}
