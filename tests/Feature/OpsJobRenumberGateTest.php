<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\User;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * POST /ops-jobs/{id}/renumber rewrites the driver route (visiting order).
 * It must carry the same gate as the Route page's Renumber button — the
 * 'admin-access operations' permission, and at least one item not yet
 * delivered (a completed job's visiting order is history). Regression: the
 * Claude-JSON panel exposed the endpoint to any authenticated user.
 */
class OpsJobRenumberGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeJob(int $itemStatus): array
    {
        $operator = Operator::create(['code' => 'OP1', 'name' => 'Test Operator']);
        $user = User::factory()->create(['operator_id' => $operator->id]);
        $customer = Customer::create(['name' => 'Site', 'operator_id' => $operator->id]);
        $vend = Vend::create(['code' => 'V901', 'operator_id' => $operator->id]);

        $job = OpsJob::create([
            'code' => 'OJ-RN1',
            'date' => Carbon::today(),
            'operator_id' => $operator->id,
            'created_by' => $user->id,
        ]);
        $item = OpsJobItem::create([
            'ops_job_id' => $job->id,
            'vend_id' => $vend->id,
            'customer_id' => $customer->id,
            'status' => $itemStatus,
            'sequence' => 9,
        ]);

        return [$user, $job, $item];
    }

    public function test_renumber_requires_the_operations_permission(): void
    {
        [$user, $job, $item] = $this->makeJob((int) OpsJob::STATUS_PENDING);

        $this->actingAs($user)
            ->post('/ops-jobs/'.$job->id.'/renumber', ['mergedOrder' => [['type' => 'item', 'id' => $item->id]]])
            ->assertForbidden();

        $this->assertEquals(9, $item->fresh()->sequence);
    }

    public function test_renumber_works_for_operations_admin_on_an_open_job(): void
    {
        [$user, $job, $item] = $this->makeJob((int) OpsJob::STATUS_PENDING);
        Permission::findOrCreate('admin-access operations', 'web');
        $user->givePermissionTo('admin-access operations');

        $this->actingAs($user)
            ->post('/ops-jobs/'.$job->id.'/renumber', ['mergedOrder' => [['type' => 'item', 'id' => $item->id]]])
            ->assertRedirect();

        $this->assertEquals(1, $item->fresh()->sequence);
    }

    public function test_renumber_stays_available_while_tasks_remain(): void
    {
        // The Route page treats every task as pending (merged with status 1),
        // so open tasks keep the route editable even after all items deliver.
        [$user, $job, $item] = $this->makeJob((int) OpsJob::STATUS_DELIVERED);
        \App\Models\OpsJobTask::forceCreate(['ops_job_id' => $job->id, 'task_name' => 'Collect keys', 'sequence' => 1, 'created_by' => $user->id]);
        Permission::findOrCreate('admin-access operations', 'web');
        $user->givePermissionTo('admin-access operations');

        $this->actingAs($user)
            ->post('/ops-jobs/'.$job->id.'/renumber', ['mergedOrder' => [['type' => 'item', 'id' => $item->id]]])
            ->assertRedirect();

        $this->assertEquals(1, $item->fresh()->sequence);
    }

    public function test_sequence_endpoint_carries_the_same_gate(): void
    {
        // /sequence performs the identical rewrite (with caller-chosen numbers)
        // — an ungated /sequence would make the /renumber gate pointless.
        [$user, $job, $item] = $this->makeJob((int) OpsJob::STATUS_PENDING);

        $this->actingAs($user)
            ->post('/ops-jobs/'.$job->id.'/sequence', ['mergedOrder' => [['type' => 'item', 'id' => $item->id, 'generated_sequence' => 5]]])
            ->assertForbidden();

        $this->assertEquals(9, $item->fresh()->sequence);
    }

    public function test_renumber_is_refused_once_every_item_is_delivered(): void
    {
        [$user, $job, $item] = $this->makeJob((int) OpsJob::STATUS_DELIVERED);
        Permission::findOrCreate('admin-access operations', 'web');
        $user->givePermissionTo('admin-access operations');

        $this->actingAs($user)
            ->post('/ops-jobs/'.$job->id.'/renumber', ['mergedOrder' => [['type' => 'item', 'id' => $item->id]]])
            ->assertForbidden();

        $this->assertEquals(9, $item->fresh()->sequence);
    }
}
