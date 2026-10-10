<?php

namespace App\Jobs;

use App\Models\DispenseRecord;
use App\Services\MqttService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishDispenseMqttLoop implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // Increase timeout to allow enough time
    public $tries = 1;

    protected $dispenseRecordID;
    protected $dataArr;
    protected $qos;
    protected $topic;
    protected $attempts;

    /**
     * Create a new job instance.
     */
    public function __construct($topic, $dataArr, $qos = 1, $dispenseRecordID, $attempts = 0)
    {
        $this->dispenseRecordID = $dispenseRecordID;
        $this->dataArr = $dataArr;
        $this->qos = $qos;
        $this->topic = $topic;
        $this->attempts = $attempts;
        // A paid customer is waiting at the machine: queue high, which supervisor-high
        // picks up the moment it is pushed (it waited 0.5 s p50, 2.9 s max on default).
        // Set here so the delayed re-sends below go to the same queue.
        $this->onQueue('high');
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

        $fid = $this->dataArr['fid'];
        $this->dataArr['result']['receivetime'] = Carbon::now()->timestamp;
        $result = $this->dataArr['result'];
        $content = base64_encode(json_encode($result));
        $contentLength = strlen($content);
        $key = $this->dataArr['key'];
        $md5 = md5($fid.','.$contentLength.','.$content.$key);

        $message = $fid.','.$contentLength.','.$content.','.$md5;

        // Publish the MQTT message
        $mqttService->publish($this->topic, $message, $this->qos);

        // Retry up to 5 times with a 10-second delay between attempts
        if ($this->attempts < 2) {
            self::dispatch($this->topic, $this->dataArr, $this->qos, $this->dispenseRecordID, $this->attempts + 1)
                ->delay(now()->addSeconds(10)); // Laravel queues handle delay instead of sleep
        }
    }
}
