<?php

namespace App\Jobs;

use App\Models\DispenseRecord;
use App\Services\MqttService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishDispenseMqttLoop implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180; // Increase timeout to allow enough time
    public $tries = 1;

    protected $dispenseRecordID;
    protected $message;
    protected $qos;
    protected $topic;
    protected $attempts;

    /**
     * Create a new job instance.
     */
    public function __construct($topic, $message, $qos = 1, $dispenseRecordID, $attempts = 0)
    {
        $this->dispenseRecordID = $dispenseRecordID;
        $this->message = $message;
        $this->qos = $qos;
        $this->topic = $topic;
        $this->attempts = $attempts;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $mqttService = new MqttService();
        $dispenseRecord = DispenseRecord::find($this->dispenseRecordID);

        // Stop execution if record is missing or if the vending machine has received the dispense signal
        if (!$dispenseRecord || $dispenseRecord->is_vm_receive_dispense_signal) {
            return;
        }

        // Publish the MQTT message
        $mqttService->publish($this->topic, $this->message, $this->qos);

        // Retry up to 5 times with a 10-second delay between attempts
        if ($this->attempts < 5) {
            self::dispatch($this->topic, $this->message, $this->qos, $this->dispenseRecordID, $this->attempts + 1)
                ->delay(now()->addSeconds(10)); // Laravel queues handle delay instead of sleep
        }
    }
}
