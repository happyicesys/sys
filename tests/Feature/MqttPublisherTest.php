<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Services\Mqtt\MqttPublisher;
use App\Services\MqttService;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\PublishedMessage;
use Tests\TestCase;

/**
 * MqttPublisher: one broker connection per worker process (2026-09-25).
 *
 * Before this, every PublishMqtt job opened a new connection — the queue worker
 * clears facades after every job and the package binds its connection manager
 * non-singleton — ~8 connections/s on the SG broker. Pinned here: reuse across
 * jobs, the canary gate, reconnect-once on failure (including a missing PUBACK),
 * idle replacement, a clean DISCONNECT on worker stop, and that the service and
 * the job go through the container singleton.
 */
class MqttPublisherTest extends TestCase
{
    /** @var list<array{id: string, client: Mockery\MockInterface}> */
    private array $made = [];

    /** @var list<array{topic: string, qos: int}> */
    private array $legacy = [];

    private float $now = 1000.0;

    /** @var list<string> which clients should fail their next publish / PUBACK */
    private array $failPublish = [];

    private array $noAck = [];

    private function publisher(array $codes = ['2009', '2031']): MqttPublisher
    {
        return new MqttPublisher(
            ['persistent_codes' => $codes, 'keep_alive' => 60, 'idle_reconnect_after' => 45, 'connect_timeout' => 3, 'ack_timeout' => 1],
            ['mqtt_vends' => [], 'mqtt_modems' => []],
            'mqtt_vends',
            function (string $name, string $clientId, Repository $repository) {
                $index = count($this->made);
                $client = Mockery::mock(MqttClientContract::class);
                $client->shouldReceive('isConnected')->andReturn(true)->byDefault();
                $client->shouldReceive('publish')->andReturnUsing(function ($topic, $message, $qos) use ($index, $repository) {
                    if (in_array($index, $this->failPublish, true)) {
                        throw new DataTransferException(DataTransferException::EXCEPTION_TX_DATA, 'broken pipe');
                    }
                    if ($qos > 0) {
                        $repository->addPendingOutgoingMessage(new PublishedMessage($repository->newMessageId(), $topic, $message, $qos, false));
                    }
                });
                // The broker "answers" on the first poll: clear what is pending,
                // unless this client is set up to lose its PUBACK.
                $client->shouldReceive('loopOnce')->andReturnUsing(function () use ($index, $repository) {
                    if (! in_array($index, $this->noAck, true)) {
                        foreach ($repository->getPendingOutgoingMessagesLastSentBefore(new \DateTime('+1 hour')) as $pending) {
                            $repository->removePendingOutgoingMessage($pending->getMessageId());
                        }
                    }
                })->byDefault();
                $client->shouldReceive('disconnect')->byDefault();
                $this->made[] = ['id' => $clientId, 'client' => $client];

                return $client;
            },
            function (string $topic, string $message, int $qos, ?string $connection) {
                $this->legacy[] = compact('topic', 'qos');
            },
            fn () => $this->now,
        );
    }

    public function test_canary_machines_use_the_persistent_connection_and_the_rest_the_old_path(): void
    {
        $publisher = $this->publisher();

        $publisher->publish('CM2031', '1,4,MQ==', 0);
        $publisher->publish('CM2009', 'frame', 1);
        $publisher->publish('CM2855', 'frame', 1);
        $publisher->publish('CX988710', 'modem', 1, 'mqtt_modems');

        $this->assertCount(1, $this->made, 'both canary machines share one connection');
        $this->assertSame([['topic' => 'CM2855', 'qos' => 1], ['topic' => 'CX988710', 'qos' => 1]], $this->legacy);
    }

    public function test_star_moves_every_topic_and_connection_onto_persistent_connections(): void
    {
        $publisher = $this->publisher(['*']);

        $publisher->publish('CM2855', 'a', 1);
        $publisher->publish('CM2289', 'b', 0);
        $publisher->publish('CX988710', 'c', 1, 'mqtt_modems');

        $this->assertSame([], $this->legacy);
        $this->assertCount(2, $this->made, 'one connection per broker connection name');
    }

    public function test_one_connection_serves_many_jobs_across_the_workers_facade_reset(): void
    {
        $publisher = $this->publisher();

        foreach (range(1, 5) as $i) {
            $publisher->publish('CM2031', "frame {$i}", 1);
            Facade::clearResolvedInstances(); // what the queue worker does after every job
            $this->now += 5;
        }

        $this->assertCount(1, $this->made);
        $this->made[0]['client']->shouldHaveReceived('publish')->times(5);
    }

    public function test_a_qos1_publish_polls_until_its_puback_and_qos0_does_not_wait(): void
    {
        $publisher = $this->publisher();

        $publisher->publish('CM2031', 'frame', 1);
        $this->made[0]['client']->shouldHaveReceived('loopOnce')->once();

        $publisher->publish('CM2031', 'ack', 0);
        $this->made[0]['client']->shouldHaveReceived('loopOnce')->once(); // still once
    }

    public function test_a_broken_socket_reconnects_once_and_publishes_again(): void
    {
        $publisher = $this->publisher();
        $this->failPublish = [0];

        $publisher->publish('CM2031', 'frame', 1);

        $this->assertCount(2, $this->made);
        $this->made[1]['client']->shouldHaveReceived('publish')->once();
    }

    public function test_a_missing_puback_counts_as_a_failure(): void
    {
        $publisher = $this->publisher();
        $this->noAck = [0];

        $publisher->publish('CM2009', 'frame', 1);

        $this->assertCount(2, $this->made, 'the unacknowledged connection was replaced');
        $this->made[1]['client']->shouldHaveReceived('publish')->once();
    }

    public function test_a_second_failure_propagates_so_the_job_fails_visibly(): void
    {
        $publisher = $this->publisher();
        $this->failPublish = [0, 1];

        $this->expectException(DataTransferException::class);
        $publisher->publish('CM2031', 'frame', 1);
    }

    public function test_an_idle_connection_is_replaced_before_it_is_used(): void
    {
        $publisher = $this->publisher();

        $publisher->publish('CM2031', 'first', 1);
        $this->now += 50; // longer than idle_reconnect_after (45)
        $publisher->publish('CM2031', 'second', 1);

        $this->assertCount(2, $this->made);
        $this->made[0]['client']->shouldHaveReceived('disconnect')->once();
    }

    public function test_a_stopping_worker_disconnects_cleanly(): void
    {
        $publisher = $this->publisher();
        $this->app->instance(MqttPublisher::class, $publisher);
        $publisher->publish('CM2031', 'frame', 1);

        Event::dispatch(new WorkerStopping(0));

        $this->made[0]['client']->shouldHaveReceived('disconnect')->once();
    }

    public function test_client_ids_fit_the_mqtt_31_limit(): void
    {
        $publisher = $this->publisher(['*']);
        $publisher->publish('CM2031', 'a', 0);
        $publisher->publish('CX1', 'b', 0, 'mqtt_modems');

        foreach ($this->made as $made) {
            $this->assertMatchesRegularExpression('/^mk1pub-[vm]-\d+-[0-9a-f]{0,4}$/', $made['id']);
            $this->assertLessThanOrEqual(23, strlen($made['id']));
        }
    }

    public function test_the_service_and_the_job_publish_through_the_container_publisher(): void
    {
        (new MqttService)->publishVend(new \App\Models\Vend(['code' => 2031]), 12, ['Type' => 'OTA_CHECK']);
        (new PublishMqtt('CM2009', 'hello', 0))->handle(app(MqttService::class));

        $this->assertCount(1, $this->mqtt->publishedTo('CM2031'));
        $this->assertSame([['topic' => 'CM2009', 'message' => 'hello', 'qos' => 0, 'connection' => null]], $this->mqtt->publishedTo('CM2009'));
    }

    public function test_the_container_hands_every_resolve_the_same_publisher(): void
    {
        $this->app->forgetInstance(MqttPublisher::class);
        $first = $this->app->make(MqttPublisher::class);
        Facade::clearResolvedInstances();
        $this->app->forgetScopedInstances();

        $this->assertSame($first, $this->app->make(MqttPublisher::class));
    }
}
