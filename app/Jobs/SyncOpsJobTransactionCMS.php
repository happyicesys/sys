<?php

namespace App\Jobs;

use App\Models\OpsJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
                'customer',
                'deliveredBy',
                'opsJobItems.opsJobItemChannels.vendChannel.product'
            ])
            ->find($this->opsJobID);

        $data = [
            'date' => Carbon::parse($opsJob->date)->format('Y-m-d'),
            'driver' => $opsJob->deliveredBy->username,
            'cms_person_id' => $opsJob->customer->person_id,
            'created_by' => auth()->user()->username,
            'status' => 'Delivered',
            'transaction_id' => $opsJob->cms_transaction_id,
            'items' => [],
        ];

        if($opsJob->opsJobItems) {
            foreach($opsJob->opsJobItems as $opsJobItem) {
                if($opsJobItem->cms_transaction_id) {
                    continue;
                }

                $data = [
                    'date' => Carbon::parse($date)->format('Y-m-d'),
                    'driver' => $driver->username,
                    'cms_person_id' => $opsJobItem->customer->person_id,
                    'created_by' => auth()->user()->username,
                    'status' => 'Delivered',
                    'transaction_id' => $opsJobItem->cms_transaction_id,
                    'items' => [],
                ];

                if($opsJobItem->opsJobItemChannels) {
                    foreach($opsJobItem->opsJobItemChannels as $opsJobItemChannel) {
                        if($opsJobItemChannel->actual_qty > 0) {
                            $data['items'][$opsJobItemChannel->vend_channel_code] = [
                                'product_code' => $opsJobItemChannel->vendChannel->product->code,
                                'capacity' => $opsJobItemChannel->capacity,
                                'qty' => $opsJobItemChannel->vendChannel->qty,
                                'needed' => $opsJobItemChannel->actual_qty,
                            ];
                        }
                    }
                }

                $response = Http::post($this->endpoint, $data);
            }
        }
    }
}
