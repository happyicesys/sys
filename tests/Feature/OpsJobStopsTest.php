<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\OpsJobTask;
use App\Models\ServiceNotice;
use App\Models\StockCheck;
use App\Models\User;
use App\Models\Vend;
use App\Support\OpsJobStopRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * An ops job now carries four kinds of row. The visiting order is written
 * through ONE registry, and the job page's machine dropdown serves all of the
 * machine-bound kinds.
 */
class OpsJobStopsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private OpsJob $job;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();

        $operator = Operator::create(['code' => 'OP1', 'name' => 'Operator One']);
        $this->user = User::factory()->create(['operator_id' => $operator->id]);
        foreach (['read operations', 'update operations', 'admin-access operations'] as $permission) {
            Permission::findOrCreate($permission, 'web');
            $this->user->givePermissionTo($permission);
        }
        $customer = Customer::create(['name' => 'Site A', 'operator_id' => $operator->id]);
        $this->vend = Vend::create(['code' => 'V901', 'operator_id' => $operator->id, 'customer_id' => $customer->id]);
        $this->job = OpsJob::create(['code' => 90001, 'date' => Carbon::today(), 'operator_id' => $operator->id, 'created_by' => $this->user->id]);
    }

    private function stops(OpsJob $job, int $itemStatus = 1): array
    {
        $base = ['ops_job_id' => $job->id, 'vend_id' => $this->vend->id, 'customer_id' => $this->vend->customer_id];
        $code = 10000 + $job->id; // (operator_id, code) is unique per stop table

        return [
            'item' => OpsJobItem::create($base + ['status' => $itemStatus, 'sequence' => 9]),
            'task' => OpsJobTask::forceCreate(['ops_job_id' => $job->id, 'task_name' => 'Keys', 'sequence' => 9, 'created_by' => $this->user->id]),
            'service_notice' => ServiceNotice::create($base + ['code' => $code, 'operator_id' => $job->operator_id, 'sequence' => 9]),
            'stock_check' => StockCheck::create($base + ['code' => $code, 'operator_id' => $job->operator_id, 'sequence' => 9]),
        ];
    }

    public function test_renumber_orders_all_four_kinds_of_row(): void
    {
        $stops = $this->stops($this->job);
        $order = ['stock_check', 'item', 'service_notice', 'task'];

        $this->actingAs($this->user)->post('/ops-jobs/'.$this->job->id.'/renumber', [
            'mergedOrder' => array_map(fn ($type) => ['type' => $type, 'id' => $stops[$type]->id], $order),
        ])->assertRedirect();

        foreach ($order as $index => $type) {
            $this->assertEquals($index + 1, $stops[$type]->fresh()->sequence, $type);
        }
    }

    public function test_sequence_save_writes_the_callers_numbers_for_the_new_kinds(): void
    {
        $stops = $this->stops($this->job);

        $this->actingAs($this->user)->post('/ops-jobs/'.$this->job->id.'/sequence', ['mergedOrder' => [
            ['type' => 'service_notice', 'id' => $stops['service_notice']->id, 'generated_sequence' => 2.5],
            ['type' => 'stock_check', 'id' => $stops['stock_check']->id, 'generated_sequence' => 4],
        ]])->assertRedirect();

        $this->assertEquals(2.5, $stops['service_notice']->fresh()->sequence);
        $this->assertEquals(4, $stops['stock_check']->fresh()->sequence);
    }

    public function test_a_stop_of_another_job_is_never_moved(): void
    {
        $otherJob = OpsJob::create(['code' => 90002, 'date' => Carbon::today(), 'operator_id' => $this->job->operator_id, 'created_by' => $this->user->id]);
        $foreign = $this->stops($otherJob);
        $this->stops($this->job);

        $this->actingAs($this->user)->post('/ops-jobs/'.$this->job->id.'/renumber', ['mergedOrder' => [
            ['type' => 'service_notice', 'id' => $foreign['service_notice']->id],
            ['type' => 'stock_check', 'id' => $foreign['stock_check']->id],
        ]])->assertRedirect();

        $this->assertEquals(9, $foreign['service_notice']->fresh()->sequence);
        $this->assertEquals(9, $foreign['stock_check']->fresh()->sequence);
    }

    public function test_an_open_notice_keeps_a_delivered_jobs_route_editable(): void
    {
        $item = OpsJobItem::create(['ops_job_id' => $this->job->id, 'vend_id' => $this->vend->id, 'status' => (int) OpsJob::STATUS_DELIVERED, 'sequence' => 9]);
        $payload = ['mergedOrder' => [['type' => 'item', 'id' => $item->id]]];

        $this->actingAs($this->user)->post('/ops-jobs/'.$this->job->id.'/renumber', $payload)->assertForbidden();

        ServiceNotice::create(['ops_job_id' => $this->job->id, 'vend_id' => $this->vend->id, 'code' => 10001, 'operator_id' => $this->job->operator_id]);

        $this->actingAs($this->user)->post('/ops-jobs/'.$this->job->id.'/renumber', $payload)->assertRedirect();
    }

    public function test_an_unknown_type_is_treated_as_an_item_like_the_legacy_payload(): void
    {
        $this->assertSame(OpsJobItem::class, OpsJobStopRegistry::modelFor(null));
        $this->assertSame(OpsJobItem::class, OpsJobStopRegistry::modelFor('nonsense'));
        $this->assertSame(StockCheck::class, OpsJobStopRegistry::modelFor('stock_check'));
    }

    public function test_the_job_page_lists_a_machine_already_on_the_job_and_flags_it(): void
    {
        $free = Vend::create(['code' => 'V902', 'operator_id' => $this->job->operator_id, 'customer_id' => $this->vend->customer_id]);
        $this->stops($this->job);

        $this->actingAs($this->user)->get('/ops-jobs/'.$this->job->id.'/edit')
            ->assertInertia(fn ($page) => $page->component('OpsJob/Edit')
                ->where('unbindedVendOptions.data', fn ($options) => collect($options)->pluck('in_job', 'id')->all() === [
                    $this->vend->id => true, $free->id => false,
                ])
                ->has('opsJob.data.serviceNotices', 1)
                ->has('opsJob.data.stockChecks', 1)
                ->where('opsJob.data.serviceNotices.0.display_code', 'SN-'.(10000 + $this->job->id))
                ->where('opsJob.data.stockChecks.0.stop_type', 'stock_check'));
    }
}
