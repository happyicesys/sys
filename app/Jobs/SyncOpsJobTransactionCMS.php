<?php

namespace App\Jobs;

use App\Models\OpsJob;
use App\Services\OpsJobService;
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

    protected $data;
    protected $opsJobItem;
    protected $opsJobService;
    protected $endpoint;
    /**
     * Create a new job instance.
     */
    public function __construct($opsJobItem, $data)
    {
        $this->data = $data;
        $this->opsJobItem = $opsJobItem;
        $this->opsJobService = new OpsJobService();
        $this->endpoint = env('CMS_URL') . '/api/transactions/deals';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $data = $this->data;
        $opsJobItem = $this->opsJobItem;

        if($opsJobItem->customer && $opsJobItem->customer->person_id) {
            $data['customers'][$opsJobItem->customer->person_id] = [
                'ops_job_item_id' => $opsJobItem->id,
                'attachments' => [],
                'cash_collected' => $opsJobItem->cash_amount ? $opsJobItem->cash_amount : 0,
                'channels' => [],
                'sequence' => $opsJobItem->sequence,
            ];

            if($opsJobItem->opsJobItemChannels) {
                foreach($opsJobItem->opsJobItemChannels as $opsJobItemChannel) {
                    if($opsJobItemChannel->actual_qty > 0) {
                        $data['customers'][$opsJobItem->customer->person_id]['channels'][$opsJobItemChannel->vend_channel_code] = [
                            'amount' => $opsJobItemChannel->vendChannel->qty * $opsJobItemChannel->vendChannel->amount,
                            'unit_price' => $opsJobItemChannel->vendChannel->amount,
                            'product_code' => $opsJobItemChannel->vendChannel->product->code,
                            'capacity' => $opsJobItemChannel->capacity,
                            'qty' => $opsJobItemChannel->vendChannel->qty,
                            'needed' => $opsJobItemChannel->actual_qty,
                        ];
                    }
                }
            }

            if($opsJobItem->attachments) {
                foreach($opsJobItem->attachments as $attachment) {
                    $data['customers'][$opsJobItem->customer->person_id]['attachments'][$attachment->id] = [
                        'created_at' => $attachment->created_at,
                        'name' => $attachment->name,
                        'sequence' => $attachment->sequence,
                        'url' => $attachment->full_url,
                    ];
                }
            }
        }

        $response = Http::post($this->endpoint, $data);

        if($response->successful()) {
            $this->opsJobService->updateJobItemCMSTransactionID($response->json());
        }
    }
}
