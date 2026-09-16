<?php

namespace App\Jobs;

use App\Services\MqttService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishMqtt implements ShouldQueue
{
    public $timeout = 5;

    public $tries = 1;

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mqttConnection; // Renamed to avoid conflict

    protected $message;

    protected $qos;

    protected $topic;

    /**
     * Create a new job instance.
     */
    public function __construct($topic, $message, $qos = 1, $mqttConnection = null)
    {
        $this->mqttConnection = $mqttConnection;
        $this->message = $message;
        $this->qos = $qos;
        $this->topic = $topic;
    }

    /**
     * Execute the job. The service is resolved here, not serialised into
     * every ack payload from the constructor.
     */
    public function handle(MqttService $mqttService): void
    {
        $mqttService->publish($this->topic, $this->message, $this->qos, $this->mqttConnection);
    }
}
