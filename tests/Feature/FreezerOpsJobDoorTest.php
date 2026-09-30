<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Models\Customer;
use App\Models\FreezerControlCommand;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\User;
use App\Models\Vend;
use App\Services\Freezer\FreezerControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Open Door (Restock) on a Smart Freezer ops-job item: FREEZERCTL `unlock`, driver-level access
 * (the CityBox rule), answered asynchronously.
 */
class FreezerOpsJobDoorTest extends TestCase
{
    use RefreshDatabase;

    protected Vend $vend;

    protected User $driver;

    protected OpsJob $job;

    protected OpsJobItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake([PublishMqtt::class]);
        Permission::findOrCreate('update operations', 'web');

        $customer = Customer::create(['name' => 'Freezer Site', 'code' => 10002, 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        $this->vend = Vend::create(['code' => 50091, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1,
            'customer_id' => $customer->id]);
        $this->vend->forceFill(['apk_version_code' => 23, 'is_online' => 1])->save();
        $this->driver = User::factory()->create();
        $this->job = OpsJob::create(['code' => 900200, 'date' => now()->toDateString(), 'status' => 1, 'delivered_by' => $this->driver->id, 'operator_id' => 1]);
        $this->item = OpsJobItem::create(['ops_job_id' => $this->job->id, 'vend_id' => $this->vend->id, 'customer_id' => $customer->id, 'status' => OpsJob::STATUS_PICKED]);
    }

    public function test_assigned_driver_opens_the_door_with_an_unlock_command_and_others_cannot(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->postJson("/ops-jobs/items/{$this->item->id}/freezer-open-door")->assertForbidden();

        $cmdId = $this->actingAs($this->driver)->postJson("/ops-jobs/items/{$this->item->id}/freezer-open-door")
            ->assertOk()->assertJson(['status' => 'pending'])->json('cmd_id');

        $command = FreezerControlCommand::sole();
        $this->assertSame([$cmdId, 'unlock', FreezerControlCommand::SOURCE_OPS_JOB, $this->driver->id, $this->vend->id],
            [$command->cmd_id, $command->op, $command->source, $command->requested_by, $command->vend_id]);
        Queue::assertPushed(PublishMqtt::class, 1);

        // An ops user who is not the driver may too.
        $ops = User::factory()->create();
        $ops->givePermissionTo('update operations');
        $this->travel(30)->seconds(); // past the per-item double-tap guard
        $this->actingAs($ops)->postJson("/ops-jobs/items/{$this->item->id}/freezer-open-door")->assertOk();
        $this->assertSame(2, FreezerControlCommand::count());
    }

    public function test_double_tap_is_rate_limited(): void
    {
        $this->actingAs($this->driver)->postJson("/ops-jobs/items/{$this->item->id}/freezer-open-door")->assertOk();
        $this->actingAs($this->driver)->postJson("/ops-jobs/items/{$this->item->id}/freezer-open-door")->assertStatus(429);
        $this->assertSame(1, FreezerControlCommand::count());
    }

    public function test_status_reports_the_freezers_answer_then_timeout(): void
    {
        $cmdId = $this->actingAs($this->driver)->postJson("/ops-jobs/items/{$this->item->id}/freezer-open-door")->json('cmd_id');
        $url = "/ops-jobs/items/{$this->item->id}/freezer-open-door/{$cmdId}";

        $this->actingAs($this->driver)->getJson($url)->assertOk()->assertJson(['status' => 'pending']);

        FreezerControlCommand::where('cmd_id', $cmdId)->update(['status' => 'busy', 'response_msg' => 'a sale is in progress']);
        $this->actingAs($this->driver)->getJson($url)->assertJson(['status' => 'busy', 'message' => 'a sale is in progress']);

        FreezerControlCommand::where('cmd_id', $cmdId)->update(['status' => 'pending', 'response_msg' => null]);
        $this->travel(FreezerControlService::TTL_SECONDS + 31)->seconds();
        $this->actingAs($this->driver)->getJson($url)->assertJson(['status' => FreezerControlCommand::STATUS_TIMEOUT]);
    }

    public function test_status_cannot_read_another_machines_command(): void
    {
        $other = Vend::create(['code' => 50092, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $foreign = app(FreezerControlService::class)->dispatch($other, 'unlock', [], null, null);

        $this->actingAs($this->driver)->getJson("/ops-jobs/items/{$this->item->id}/freezer-open-door/{$foreign->cmd_id}")->assertNotFound();
    }

    public function test_an_app_too_old_for_remote_controls_is_refused_without_sending(): void
    {
        $this->vend->forceFill(['apk_version_code' => FreezerControlService::minApkVersionFor('unlock') - 1])->save();

        $this->actingAs($this->driver)->postJson("/ops-jobs/items/{$this->item->id}/freezer-open-door")->assertStatus(422);
        $this->assertSame(0, FreezerControlCommand::count());
        Queue::assertNothingPushed();
    }

    public function test_route_is_403_for_a_vending_machine_item(): void
    {
        $vm = Vend::create(['code' => 9603, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1]);
        $vmItem = OpsJobItem::create(['ops_job_id' => $this->job->id, 'vend_id' => $vm->id, 'customer_id' => $this->vend->customer_id, 'status' => 2]);

        $this->actingAs($this->driver)->postJson("/ops-jobs/items/{$vmItem->id}/freezer-open-door")->assertForbidden();
    }

    public function test_resource_flags_a_freezer_item(): void
    {
        $item = $this->item->load('vend');
        $payload = \App\Http\Resources\OpsJobItemResource::make($item)->resolve();

        $this->assertTrue($payload['is_smart_freezer']);
        $this->assertFalse($payload['is_citybox_chiller']);
        $this->assertSame([], $payload['disallowed_stock_actions']); // melted stock applies: it sells ice cream
    }
}
