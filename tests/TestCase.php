<?php

namespace Tests;

use App\Services\Mqtt\MqttPublisher;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeMqttPublisher;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /** Every frame the code under test "sent" to a machine; see FakeMqttPublisher. */
    protected FakeMqttPublisher $mqtt;

    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach a real broker. phpunit runs the queue synchronously and
        // local .env files point at the production broker, so before 2026-09-25 any
        // test that dispatched PublishMqtt without faking the queue really published
        // (seen on the SG broker: CM5001 / CM5002 from developer machines).
        $this->mqtt = new FakeMqttPublisher;
        $this->app->instance(MqttPublisher::class, $this->mqtt);
    }
}
