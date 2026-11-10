<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Models\FreezerControlCommand;
use App\Models\User;
use App\Models\Vend;
use App\Services\Freezer\FreezerControlService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Advance Control (Vend/Edit) on a smart freezer (Brian, 2026-11-10). The vending buttons sent frames the
 * freezer app ignores (RESET, REBOOTANDROID, UPDATELOG), so Restart APK now sends the freezer's own
 * `restart` op and reports its answer, and Restart VMC / Trigger Log Upload are refused. Vending boards
 * are unchanged.
 */
class FreezerAdvanceControlTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        // Advance Control's audience: the page is reached from Machine View, no machine-settings rights.
        $this->user = User::factory()->create(['name' => 'Ops One', 'operator_id' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('read machine-view', 'web'));
        $this->actingAs($this->user);
    }

    private function machine(array $attrs = []): Vend
    {
        $attrs = array_merge([
            'code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1,
            'apk_version_code' => 55, 'private_key' => 'TESTKEY000000001',
        ], $attrs);
        $vend = new Vend;
        $vend->forceFill($attrs)->save();

        return $vend->refresh();
    }

    /** @return array<int, array> decoded JSON of every published frame */
    private function publishedFrames(): array
    {
        $frames = [];
        Queue::assertPushed(PublishMqtt::class, function (PublishMqtt $job) use (&$frames) {
            [$topic, $wire] = (fn () => [$this->topic, $this->message])->call($job);
            $b64 = explode(',', $wire)[2];
            $frames[] = ['topic' => $topic] + json_decode(base64_decode($b64), true);

            return true;
        });

        return $frames;
    }

    public function test_restart_app_sends_the_freezer_restart_op_and_records_who_asked(): void
    {
        $vend = $this->machine();

        $this->postJson("/vends/{$vend->id}/freezer-restart-app")
            ->assertStatus(202)->assertJsonPath('status', 'pending');

        $row = FreezerControlCommand::sole();
        $this->assertSame('restart', $row->op);
        $this->assertSame('Ops One', $row->requested_by_name);

        $frames = $this->publishedFrames();
        $this->assertCount(1, $frames);
        $this->assertSame('CM50001', $frames[0]['topic']);
        $this->assertSame('FREEZERCTL', $frames[0]['Type']);
        $this->assertSame('restart', $frames[0]['op']);
        $this->assertSame($row->cmd_id, $frames[0]['cmdId']);
    }

    public function test_status_follows_the_machines_answer(): void
    {
        $vend = $this->machine();
        $id = $this->postJson("/vends/{$vend->id}/freezer-restart-app")->json('id');
        $row = FreezerControlCommand::findOrFail($id);

        $this->getJson("/vends/{$vend->id}/freezer-restart-app/{$id}")->assertOk()->assertJsonPath('status', 'pending');

        app(FreezerControlService::class)->recordAck($vend, ['cmdId' => $row->cmd_id, 'result' => 'busy', 'msg' => 'sale in progress']);

        $this->getJson("/vends/{$vend->id}/freezer-restart-app/{$id}")
            ->assertOk()->assertJsonPath('status', 'busy')->assertJsonPath('message', 'sale in progress');
    }

    public function test_an_unanswered_restart_reads_timeout_and_a_new_one_is_allowed(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $vend = $this->machine();
        $id = $this->postJson("/vends/{$vend->id}/freezer-restart-app")->json('id');

        // Still waiting: a second tap is refused, never queued behind the first.
        $this->postJson("/vends/{$vend->id}/freezer-restart-app")
            ->assertStatus(422)->assertJsonValidationErrors('restart');

        Carbon::setTestNow('2026-11-10 10:02:00');
        $this->getJson("/vends/{$vend->id}/freezer-restart-app/{$id}")->assertJsonPath('status', 'timeout');
        $this->postJson("/vends/{$vend->id}/freezer-restart-app")->assertStatus(202);
    }

    public function test_an_app_before_14_is_refused_and_nothing_is_sent(): void
    {
        $vend = $this->machine(['apk_version_code' => 13]);

        $this->postJson("/vends/{$vend->id}/freezer-restart-app")
            ->assertStatus(422)->assertJsonValidationErrors('restart');

        $this->assertSame(0, FreezerControlCommand::count());
        Queue::assertNotPushed(PublishMqtt::class);
    }

    public function test_restart_app_is_for_freezers_only_and_status_only_for_its_own_restarts(): void
    {
        $vending = $this->machine(['code' => 2031, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE]);
        $this->postJson("/vends/{$vending->id}/freezer-restart-app")->assertNotFound();

        $a = $this->machine();
        $b = $this->machine(['code' => 50002]);
        $id = $this->postJson("/vends/{$a->id}/freezer-restart-app")->json('id');
        $this->getJson("/vends/{$b->id}/freezer-restart-app/{$id}")->assertNotFound();

        $other = app(FreezerControlService::class)->dispatch($b, 'status', [], $this->user, null);
        $this->getJson("/vends/{$b->id}/freezer-restart-app/{$other->id}")->assertNotFound();
    }

    public function test_a_user_outside_advance_control_cannot_restart(): void
    {
        $vend = $this->machine();
        $this->actingAs(User::factory()->create(['operator_id' => 1]));

        $this->postJson("/vends/{$vend->id}/freezer-restart-app")->assertForbidden();
        Queue::assertNotPushed(PublishMqtt::class);
    }

    public function test_the_old_restart_apk_route_on_a_freezer_sends_restart_not_rebootandroid(): void
    {
        $vend = $this->machine();

        $this->from("/vends/{$vend->id}/edit")->post("/vends/{$vend->id}/restart-apk")->assertRedirect();

        $frames = $this->publishedFrames();
        $this->assertCount(1, $frames);
        $this->assertSame('FREEZERCTL', $frames[0]['Type']);
        $this->assertSame('restart', $frames[0]['op']);
    }

    public function test_restart_vmc_and_log_upload_are_refused_on_a_freezer(): void
    {
        $vend = $this->machine();

        $this->from("/vends/{$vend->id}/edit")->post("/vends/{$vend->id}/restart-vmc")
            ->assertRedirect()->assertSessionHasErrors('restart');
        $this->from("/vends/{$vend->id}/edit")->post("/vends/{$vend->id}/trigger-log-upload")
            ->assertRedirect()->assertSessionHasErrors('trigger_log_date');

        Queue::assertNotPushed(PublishMqtt::class);
    }

    public function test_vending_boards_keep_their_frames(): void
    {
        $vend = $this->machine(['code' => 2031, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE]);

        $this->post("/vends/{$vend->id}/restart-vmc");
        $this->post("/vends/{$vend->id}/restart-apk");
        $this->post("/vends/{$vend->id}/trigger-log-upload");

        $types = array_column($this->publishedFrames(), 'Type');
        sort($types);
        $this->assertSame(['REBOOTANDROID', 'RESET', 'UPDATELOG'], $types);
        $this->assertSame(0, FreezerControlCommand::count());
    }
}
