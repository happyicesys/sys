<?php

namespace Tests\Feature;

use App\Jobs\PublishDispenseMqttLoop;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\ProvisioningPlan;
use Tests\TestCase;

/**
 * Payment-path jobs on `high` are picked up the moment they land (2026-10-10): a
 * dedicated supervisor pops `high` through a blocking connection. A Horizon config
 * error stops every queue, so the plan production will start is pinned here.
 */
class HorizonPaymentQueueTest extends TestCase
{
    public function test_production_runs_two_blocking_workers_on_high(): void
    {
        $plan = ProvisioningPlan::get('test-master')->toSupervisorOptions();

        $high = $plan['production']['supervisor-high'];
        $this->assertSame('redis-high', $high->connection);
        $this->assertSame('high', $high->queue);
        $this->assertSame(2, $high->maxProcesses);
        $this->assertSame(1, $high->maxTries);

        $command = $high->toWorkerCommand();
        $this->assertStringContainsString('horizon:work redis-high', $command);
        $this->assertStringContainsString('--queue="high"', $command);
    }

    public function test_the_shared_pool_still_serves_high_and_default(): void
    {
        $plan = ProvisioningPlan::get('test-master')->toSupervisorOptions();

        $shared = $plan['production']['supervisor-1'];
        $this->assertSame('redis', $shared->connection);
        $this->assertSame('high,default', $shared->queue);
        $this->assertSame(35, $shared->maxProcesses);
    }

    public function test_redis_high_blocks_and_the_shared_connection_never_does(): void
    {
        $this->assertSame(5, config('queue.connections.redis-high.block_for'));
        $this->assertSame('default', config('queue.connections.redis-high.connection'));
        $this->assertNull(config('queue.connections.redis.block_for'));

        $queue = app('queue')->connection('redis-high');
        $this->assertInstanceOf(RedisQueue::class, $queue);
        // Same Redis list the app pushes to through `redis`.
        $this->assertSame('queues:high', $queue->getQueue('high'));
        $this->assertSame('queues:high', app('queue')->connection('redis')->getQueue('high'));
    }

    public function test_the_dispense_command_and_its_resends_go_to_high(): void
    {
        Queue::fake();

        PublishDispenseMqttLoop::dispatch('CM2009', ['fid' => 1], 1, 42);

        Queue::assertPushedOn('high', PublishDispenseMqttLoop::class);
        $this->assertSame('high', (new PublishDispenseMqttLoop('CM2009', [], 1, 42, 1))->queue);
    }
}
