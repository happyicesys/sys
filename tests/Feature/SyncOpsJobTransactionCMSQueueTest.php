<?php

namespace Tests\Feature;

use App\Jobs\SyncOpsJobTransactionCMS;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\User;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncOpsJobTransactionCMSQueueTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(): array
    {
        $operator = Operator::create(['code' => 'OP1', 'name' => 'Test Operator']);
        $user = User::factory()->create(['operator_id' => $operator->id, 'username' => 'driver1']);
        $customer = Customer::create(['name' => 'Site', 'operator_id' => $operator->id, 'person_id' => 777]);
        $vend = Vend::create(['code' => 'V001', 'operator_id' => $operator->id]);
        $opsJob = OpsJob::create([
            'code' => 'OJ-001',
            'date' => Carbon::today(),
            'operator_id' => $operator->id,
            'created_by' => $user->id,
            'delivered_by' => $user->id,
        ]);
        $item = OpsJobItem::create([
            'ops_job_id' => $opsJob->id,
            'vend_id' => $vend->id,
            'customer_id' => $customer->id,
            'status' => OpsJob::STATUS_DELIVERED,
        ]);

        return [$user, $item];
    }

    private function job(User $user, OpsJobItem $item): SyncOpsJobTransactionCMS
    {
        return new SyncOpsJobTransactionCMS($item, [
            'date' => Carbon::today()->format('Y-m-d'),
            'driver' => $user->username,
            'created_by' => $user->username,
            'status' => 'Delivered',
            'customers' => [],
        ], $user->id);
    }

    public function test_it_is_queued_on_the_throttled_cms_queue_with_backoff()
    {
        Queue::fake();
        [$user, $item] = $this->makeItem();

        SyncOpsJobTransactionCMS::dispatch($item, [], $user->id);

        Queue::assertPushedOn('cms', SyncOpsJobTransactionCMS::class);
        $job = $this->job($user, $item);
        $this->assertSame(3, $job->tries);
        $this->assertSame([15, 60], $job->backoff);
    }

    public function test_the_cms_queue_has_its_own_small_production_supervisor()
    {
        $defaults = config('horizon.defaults.supervisor-cms');
        $production = config('horizon.environments.production.supervisor-cms');

        $this->assertSame(['cms'], $defaults['queue']);
        $this->assertLessThanOrEqual(3, $production['maxProcesses']);
        $this->assertLessThan(config('queue.connections.redis.retry_after'), $defaults['timeout']);
        $this->assertNotContains('cms', config('horizon.defaults.supervisor-1.queue'));
    }

    public function test_success_records_the_cms_transaction_id()
    {
        config(['app.cms_url' => 'https://cms.test']);
        [$user, $item] = $this->makeItem();
        Http::fake(['cms.test/*' => Http::response([
            ['ops_job_item_id' => $item->id, 'transaction_id' => 555],
        ])]);

        $this->job($user, $item)->handle();

        $this->assertSame(555, (int) $item->fresh()->cms_transaction_id);
    }

    public function test_a_cms_error_fails_the_attempt_so_it_is_retried()
    {
        config(['app.cms_url' => 'https://cms.test']);
        [$user, $item] = $this->makeItem();
        Http::fake(['cms.test/*' => Http::response([
            'error' => 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found',
        ], 400)]);

        try {
            $this->job($user, $item)->handle();
            $this->fail('A 400 from cms must throw so the queue retries the push.');
        } catch (RequestException $e) {
            $this->assertSame(400, $e->response->status());
        }

        $this->assertNull($item->fresh()->cms_transaction_id);
    }
}
