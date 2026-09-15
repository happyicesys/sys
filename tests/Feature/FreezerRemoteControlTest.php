<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Models\FreezerControlCommand;
use App\Models\User;
use App\Models\Vend;
use App\Services\Freezer\FreezerControlService;
use App\Services\VendDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Setting/Edit remote cabinet controls for smart freezers: the FREEZERCTL frame mark1 sends, the
 * permissions around it, and the FREEZERCTLACK the device sends back.
 */
class FreezerRemoteControlTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->user = User::factory()->create(['name' => 'Tech One', 'operator_id' => 1]);
        foreach (['read machine-settings', 'update machine-settings'] as $p) {
            $this->user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $this->actingAs($this->user);
    }

    private function freezer(array $attrs = []): Vend
    {
        $attrs = array_merge([
            'code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1,
            'apk_version_code' => 11, 'private_key' => 'TESTKEY000000001',
        ], $attrs);
        $vend = new Vend;
        $vend->forceFill($attrs)->save(); // apk_version_code / private_key are device-written, not fillable

        return $vend->refresh();
    }

    /** @return array{0:string,1:array} topic and decoded JSON of the one published frame */
    private function publishedFrame(): array
    {
        $frame = null;
        Queue::assertPushed(PublishMqtt::class, function (PublishMqtt $job) use (&$frame) {
            $frame = (fn () => [$this->topic, $this->message])->call($job);

            return true;
        });
        [$topic, $wire] = $frame;
        [$fid, $len, $b64, $md5] = explode(',', $wire);
        $this->assertSame(strlen($b64), (int) $len);
        $this->assertSame(md5($fid.','.$len.','.$b64.'TESTKEY000000001'), $md5, 'frame must be signed with the vend key');

        return [$topic, json_decode(base64_decode($b64), true)];
    }

    private function ack(Vend $vend, array $payload): void
    {
        $service = new VendDataService;
        $message = 'f=6&t=5&m='.$vend->code.'&g=20&p='.base64_encode(json_encode(['Type' => 'FREEZERCTLACK'] + $payload));
        $std = $service->standardizedVendData($message, 'mqtt');
        $service->processVendData($std, $service->decodeVendData($std), '127.0.0.1', 'mqtt');
        // Queue is faked: run the dispatched ack job for real.
        Queue::assertPushed(\App\Jobs\Vend\SyncFreezerControlAck::class, function ($job) {
            $job->handle(app(FreezerControlService::class));

            return true;
        });
    }

    public function test_setpoint_sends_a_signed_frame_and_records_a_pending_command(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');
        $vend = $this->freezer();

        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'setpoint', 'args' => ['celsius' => -20]])
            ->assertStatus(202)->assertJsonPath('status', 'pending');

        [$topic, $body] = $this->publishedFrame();
        $this->assertSame('CM50001', $topic);
        $this->assertSame('FREEZERCTL', $body['Type']);
        $this->assertSame('setpoint', $body['op']);
        $this->assertSame(['celsius' => -20], $body['args']);
        $this->assertSame(Carbon::now()->timestamp + FreezerControlService::TTL_SECONDS, $body['expiresAt']);

        $row = FreezerControlCommand::sole();
        $this->assertSame($body['cmdId'], $row->cmd_id);
        $this->assertSame('Tech One', $row->requested_by_name);
    }

    public function test_out_of_range_or_malformed_args_are_rejected_before_sending(): void
    {
        $vend = $this->freezer();
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'setpoint', 'args' => ['celsius' => 2]])->assertStatus(422);
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'setpoint', 'args' => ['celsius' => -18.5]])->assertStatus(422);
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'fan', 'args' => ['on' => 'yes']])->assertStatus(422);
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'selfdestruct'])->assertStatus(422);
        Queue::assertNotPushed(PublishMqtt::class);
        $this->assertSame(0, FreezerControlCommand::count());
    }

    public function test_door_commands_need_the_door_permission(): void
    {
        $vend = $this->freezer();
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'unlock'])->assertStatus(403);
        Queue::assertNotPushed(PublishMqtt::class);

        $this->user->givePermissionTo(Permission::findOrCreate('update freezer-remote-door', 'web'));
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'unlock'])->assertStatus(202);
    }

    public function test_one_pending_command_at_a_time(): void
    {
        $vend = $this->freezer();
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'status'])->assertStatus(202);
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'light', 'args' => ['on' => true]])->assertStatus(429);
    }

    public function test_non_freezer_and_old_apk_are_refused(): void
    {
        $vm = $this->freezer(['code' => 2031, 'machine_type' => 'vending_machine']);
        $this->postJson("/vends/{$vm->id}/freezer-controls", ['op' => 'status'])->assertStatus(404);

        $old = $this->freezer(['code' => 50003, 'apk_version_code' => 9]);
        $this->postJson("/vends/{$old->id}/freezer-controls", ['op' => 'status'])->assertStatus(422);
    }

    public function test_ack_completes_the_command_and_stores_the_status_snapshot(): void
    {
        $vend = $this->freezer();
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'compressor', 'args' => ['on' => true]]);
        $row = FreezerControlCommand::sole();

        $this->ack($vend, [
            'cmdId' => $row->cmd_id, 'op' => 'compressor', 'result' => 'ok', 'msg' => '已发送压缩机状态指令',
            'status' => ['thermostat' => ['available' => true, 'celsius' => -19.6, 'compressorOn' => true]],
        ]);

        $row->refresh();
        $this->assertSame('ok', $row->status);
        $this->assertSame('已发送压缩机状态指令', $row->response_msg);
        $this->assertNotNull($row->responded_at);

        $json = $this->getJson("/vends/{$vend->id}/freezer-controls")->assertOk();
        $json->assertJsonPath('status.thermostat.celsius', -19.6)
            ->assertJsonPath('commands.0.status', 'ok')
            ->assertJsonPath('commands.0.requested_by', 'Tech One')
            ->assertJsonPath('pending', false);
    }

    public function test_a_replayed_duplicate_answer_does_not_overwrite_the_verdict(): void
    {
        $vend = $this->freezer();
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'light', 'args' => ['on' => true]]);
        $row = FreezerControlCommand::sole();
        app(FreezerControlService::class)->recordAck($vend, ['cmdId' => $row->cmd_id, 'result' => 'ok']);
        app(FreezerControlService::class)->recordAck($vend, ['cmdId' => $row->cmd_id, 'result' => 'duplicate']);
        $this->assertSame('ok', $row->refresh()->status);
    }

    public function test_another_vends_cmd_id_is_ignored(): void
    {
        $a = $this->freezer();
        $b = $this->freezer(['code' => 50002]);
        $this->postJson("/vends/{$a->id}/freezer-controls", ['op' => 'status']);
        $row = FreezerControlCommand::sole();
        app(FreezerControlService::class)->recordAck($b, ['cmdId' => $row->cmd_id, 'result' => 'ok', 'status' => ['x' => 1]]);
        $this->assertSame('pending', $row->refresh()->status);
        $this->assertNull(DB::table('vends')->where('id', $a->id)->value('freezer_control_status_json'));
    }

    public function test_unanswered_command_shows_as_timeout_after_expiry(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');
        $vend = $this->freezer();
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'status']);
        Carbon::setTestNow('2026-09-15 10:02:00');
        $this->getJson("/vends/{$vend->id}/freezer-controls")
            ->assertJsonPath('commands.0.status', 'timeout')
            ->assertJsonPath('pending', false);
        // and a new command is allowed again
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'status'])->assertStatus(202);
    }

    public function test_ack_log_excerpt_is_stored_and_shown(): void
    {
        $vend = $this->freezer();
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'fan', 'args' => ['on' => true]]);
        $row = FreezerControlCommand::sole();
        $this->ack($vend, ['cmdId' => $row->cmd_id, 'op' => 'fan', 'result' => 'ok', 'log' => '09-15 10:00:01.000 I/BoxCommandDispatcher( 1): dispatch ok action=openFan', 'logScope' => 'system']);
        $this->getJson("/vends/{$vend->id}/freezer-controls")
            ->assertJsonPath('commands.0.log_scope', 'system')
            ->assertJsonFragment(['log' => '09-15 10:00:01.000 I/BoxCommandDispatcher( 1): dispatch ok action=openFan']);
    }

    public function test_kiosk_panel_controls_are_filed_in_the_same_log(): void
    {
        $vend = $this->freezer();
        $this->ack($vend, ['cmdId' => 'panel-abc123', 'source' => 'panel', 'op' => 'set temperature -20°C', 'result' => 'refused', 'msg' => '不支持', 'log' => 'x', 'logScope' => 'app']);
        $row = FreezerControlCommand::sole();
        $this->assertSame('panel', $row->source);
        $this->assertSame('refused', $row->status);
        $this->assertSame('Kiosk panel', $row->requested_by_name);
        // a replay of the same panel cmdId does not create a second row
        $this->ack($vend, ['cmdId' => 'panel-abc123', 'source' => 'panel', 'op' => 'set temperature -20°C', 'result' => 'ok']);
        $this->assertSame(1, FreezerControlCommand::count());
        $this->assertSame('refused', $row->refresh()->status);
    }

    public function test_machine_events_are_filed_as_timeline_rows(): void
    {
        $vend = $this->freezer();
        $this->ack($vend, ['cmdId' => 'event-b00t', 'source' => 'event', 'op' => 'boot', 'result' => 'ok', 'msg' => 'boot reason reboot,adb', 'log' => 'x', 'logScope' => 'system']);
        $this->ack($vend, ['cmdId' => 'event-err1', 'source' => 'event', 'op' => 'error:Maintenance', 'result' => 'ok', 'msg' => 'SERVICE-MODE light on REFUSED']);
        // a spoofed source without the matching cmdId prefix is not filed
        $this->ack($vend, ['cmdId' => 'random1', 'source' => 'event', 'op' => 'boot', 'result' => 'ok']);
        $this->assertSame(2, FreezerControlCommand::count());
        $this->getJson("/vends/{$vend->id}/freezer-controls")
            ->assertJsonPath('commands.0.source', 'event')
            ->assertJsonPath('commands.0.requested_by', 'Machine')
            ->assertJsonPath('commands.1.op', 'boot');
    }

    public function test_logs_command_upload_and_view(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $vend = $this->freezer();
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'logs', 'args' => ['lines' => 500, 'minutes' => 99999, 'grep' => ' Ag325 ']])->assertStatus(202);
        $row = FreezerControlCommand::sole();
        [, $body] = $this->publishedFrame();
        $this->assertSame(['lines' => 500, 'minutes' => 2880, 'grep' => 'Ag325'], $body['args']);

        $text = "09-15 10:00:00.000 I/A( 1): one\n09-15 10:00:01.000 I/B( 1): two\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('logcat.txt.gz', gzencode($text));
        // wrong cmdId: refused
        $this->post("/api/v1/vends/{$vend->code}/logs", ['cmdId' => 'nope', 'lines' => 2, 'file' => $file])->assertStatus(403);
        $this->post("/api/v1/vends/{$vend->code}/logs", ['cmdId' => $row->cmd_id, 'lines' => 2, 'file' => $file])->assertOk();
        $this->ack($vend, ['cmdId' => $row->cmd_id, 'op' => 'logs', 'result' => 'ok', 'msg' => '2 lines']);

        $json = $this->getJson("/vends/{$vend->id}/freezer-controls")->assertJsonPath('commands.0.log_file.lines', 2);
        $url = $json->json('commands.0.log_file.url');
        $this->get($url)->assertOk()->assertSee('I/B( 1): two');
        $this->get($url.'?q=i/a(')->assertOk()->assertSee('one')->assertDontSee('two');
        $this->get($url.'?download=1')->assertHeader('Content-Disposition');
    }

    public function test_prune_clears_files_and_excerpts_older_than_72_hours_but_keeps_rows(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $vend = $this->freezer();
        \Illuminate\Support\Facades\Storage::put('freezer-logs/old.gz', 'x');
        $old = FreezerControlCommand::create(['vend_id' => $vend->id, 'cmd_id' => 'old000000000000000000000001', 'op' => 'logs', 'status' => 'ok', 'log_path' => 'freezer-logs/old.gz', 'response_log' => 'old excerpt', 'response_msg' => 'kept']);
        FreezerControlCommand::where('id', $old->id)->update(['created_at' => Carbon::now()->subHours(73)]);
        $fresh = FreezerControlCommand::create(['vend_id' => $vend->id, 'cmd_id' => 'new000000000000000000000001', 'op' => 'fan', 'status' => 'ok', 'response_log' => 'fresh excerpt']);
        FreezerControlCommand::where('id', $fresh->id)->update(['created_at' => Carbon::now()->subHours(71)]);

        $this->artisan('freezer-logs:prune')->assertSuccessful();

        $old->refresh();
        $this->assertNull($old->log_path);
        $this->assertNull($old->response_log);
        $this->assertSame('kept', $old->response_msg);
        $this->assertFalse(\Illuminate\Support\Facades\Storage::exists('freezer-logs/old.gz'));
        $this->assertSame('fresh excerpt', $fresh->refresh()->response_log);
    }

    public function test_readers_without_update_permission_cannot_send(): void
    {
        $reader = User::factory()->create(['operator_id' => 1]);
        $reader->givePermissionTo(Permission::findOrCreate('read machine-settings', 'web'));
        $this->actingAs($reader);
        $vend = $this->freezer();
        $this->getJson("/vends/{$vend->id}/freezer-controls")->assertOk()->assertJsonPath('can_control', false);
        $this->postJson("/vends/{$vend->id}/freezer-controls", ['op' => 'status'])->assertStatus(403);
    }
}
