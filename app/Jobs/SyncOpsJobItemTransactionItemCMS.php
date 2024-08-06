<?php

namespace App\Jobs;

use App\Models\OpsJobItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SyncOpsJobItemTransactionItemCMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $opsJobItemID;
    protected $endpoint;
    /**
     * Create a new job instance.
     */
    public function __construct($opsJobItemID)
    {
        $this->opsJobItemID = $opsJobItemID;
        $this->endpoint = env('CMS_URL') . '/api/transactions/deals';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $opsJobItem = OpsJobItem::with(['customer', 'opsJobItemChannels.vendChannel.product'])->find($this->opsJobItemID);

        if($opsJobItem->cms_transaction_id) {
            $data = [
                'status' => 'Delivered',
                'cms_person_id' => $opsJobItem->customer->person_id,
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
