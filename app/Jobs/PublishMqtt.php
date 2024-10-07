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

    protected $connection;
    protected $message;
    protected $mqttService;
    protected $qos;
    protected $topic;
    /**
     * Create a new job instance.
     */
    public function __construct($topic, $message, $qos = 1, $connection = null)
    {
        $this->connection = $connection;
        $this->message = $message;
        $this->mqttService = new MqttService();
        $this->qos = $qos;
        $this->topic = $topic;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->mqttService->publish($this->topic, $this->message, $this->qos, $this->connection);
    }
}
