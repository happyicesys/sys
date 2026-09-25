<?php

namespace Tests\Support;

use App\Services\Mqtt\MqttPublisher;

/**
 * Records publishes instead of sending them. Bound for every test in
 * Tests\TestCase so no test run can reach a real broker.
 */
class FakeMqttPublisher extends MqttPublisher
{
    /** @var list<array{topic: string, message: string, qos: int, connection: ?string}> */
    public array $published = [];

    public function __construct()
    {
        parent::__construct([], [], 'mqtt_vends');
    }

    public function publish(string $topic, string $message, int $qos = 1, ?string $connection = null): void
    {
        $this->published[] = compact('topic', 'message', 'qos', 'connection');
    }

    /** @return list<array{topic: string, message: string, qos: int, connection: ?string}> */
    public function publishedTo(string $topic): array
    {
        return array_values(array_filter($this->published, fn ($p) => $p['topic'] === $topic));
    }
}
