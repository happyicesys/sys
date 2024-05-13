<?php

namespace App\Jobs;

use App\Models\Vend;
use App\Services\IsmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendVendTempAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dataArr;
    protected $ismsService;
    protected $vend;

    /**
     * Create a new job instance.
     */
    public function __construct(Vend $vend, $dataArr = [])
    {
        $this->dataArr = $dataArr;
        $this->ismsService = new IsmsService();
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // $phoneNumbers = $this->vend->phone_numbers;
        $phoneNumbers = ['60182269545'];
        $message = $this->generateText($this->dataArr);
        $this->ismsService->sendSms($phoneNumbers, $message);


    }

    private function generateText($dataArr)
    {
        $text = 'HappyIce:';

        if($dataArr['name'] == 'TEMP_TYPE_VARIANCE_TIER_ONE' || $dataArr['name'] == 'TEMP_TYPE_VARIANCE_TIER_TWO') {
            $text .= chr(10) . $dataArr['desc'];
            $text .= chr(10) . 'VendID ';
            $text .= $this->vend->code;
            $text .= chr(10) . 'T1 ';
            $text .= $dataArr['t1'];
            $text .= chr(10) . 'T2 ';
            $text .= $dataArr['t2'];
            $text .= chr(10) . 'Delta ' . $dataArr['variance'];
        }

        return $text;
    }
}
