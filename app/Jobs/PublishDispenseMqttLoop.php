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
    public $timeout = 60; // Adjusted timeout to prevent long-running jobs
    public $tries = 1;

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dispenseRecordID;
    protected $message;
    protected $qos;
    protected $topic;

    /**
     * Create a new job instance.
     */
    public function __construct($topic, $message, $qos = 1, $dispenseRecordID)
    {
        $this->dispenseRecordID = $dispenseRecordID;
        $this->message = $message;
        $this->qos = $qos;
        $this->topic = $topic;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $mqttService = new MqttService();
        $maxAttempts = 5;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $dispenseRecord = DispenseRecord::find($this->dispenseRecordID);

            if (!$dispenseRecord) {
                break; // Stop if the record is deleted or missing
            }

            if ($dispenseRecord->is_vm_receive_dispense_signal) {
                break; // Stop if the vending machine received the signal
            }

            $mqttService->publish($this->topic, $this->message, $this->qos);
            $attempt++;
            sleep(10);
        }
    }
}
