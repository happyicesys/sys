<?php

namespace App\Jobs;

use App\Models\OpsJob;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SyncOpsJobTransactionCMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $opsJobID;
    protected $endpoint;
    /**
     * Create a new job instance.
     */
    public function __construct($opsJobID)
    {
        $this->opsJobID = $opsJobID;
        $this->endpoint = env('CMS_URL') . '/api/transactions/deals';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $opsJob = OpsJob::query()
            ->with([
                'createdBy',
                'deliveredBy',
                'opsJobItems.customer',
                'opsJobItems.opsJobItemChannels.vendChannel.product'
            ])
            ->find($this->opsJobID);

        $data = [
            'date' => Carbon::parse($opsJob->date)->format('Y-m-d'),
            'driver' => $opsJob->deliveredBy->username,
            'created_by' => $opsJob->createdBy->username,
            'status' => 'Delivered',
            'customers' => [],
        ];

        if($opsJob->opsJobItems) {
            foreach($opsJob->opsJobItems as $opsJobItem) {
                if($opsJobItem->customer && $opsJobItem->customer->person_id) {
                    if($opsJobItem->opsJobItemChannels) {
                        foreach($opsJobItem->opsJobItemChannels as $opsJobItemChannel) {
                            if($opsJobItemChannel->actual_qty > 0) {
                                $data['customers'][$opsJobItem->customer->person_id][$opsJobItemChannel->vend_channel_code] = [
                                    'ops_job_item_id' => $opsJobItem->id,
                                    'product_code' => $opsJobItemChannel->vendChannel->product->code,
                                    'capacity' => $opsJobItemChannel->capacity,
                                    'qty' => $opsJobItemChannel->vendChannel->qty,
                                    'needed' => $opsJobItemChannel->actual_qty,
                                ];
                            }
                        }
                    }
                }
            }
        }

        $response = Http::post($this->endpoint, $data);

        dd($data, $response->body());
    }
}
