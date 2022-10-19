<?php

namespace App\Jobs;

use App\Models\VendData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessVendData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $input;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($input)
    {
        $this->input = $input;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $processedDataArr = [];
        foreach($this->input as $dataIndex => $data) {

            switch($dataIndex) {
                case 'f':
                    $processedDataArr['vend_id'] = substr($data, strpos($data, "=") + 1);
                    break;
                case 't':
                    break;
                case 'm':
                    break;
                case 'g':
                    break;
                case 'p':
                    $processedDataArr['content'] = substr($data, -1) == '!' ? base64_decode(substr_replace($data,"=",-1)) : base64_decode($data);
                    break;
                default:
            }
        }

        if($this->input) {
            $vendData = VendData::create([
                'value' => $this->input,
            ]);
        }
    }
}
