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

    public $tries = 3;  // Number of attempts
    public $retryAfter = 5; // Retry after 10 seconds

    protected $data;
    protected $opsJobItem;
    protected $opsJobService;
    protected $endpoint;
    protected $userID;
    /**
     * Create a new job instance.
     */
    public function __construct($opsJobItem, $data, $userID)
    {
        $this->data = $data;
        $this->opsJobItem = $opsJobItem;
        $this->opsJobService = new OpsJobService();
        $this->userID = $userID;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $baseUrl = config('app.cms_url');

        if (!$baseUrl) {
            return;
        }

        $this->endpoint = $baseUrl . '/api/transactions/deals';

        $data = $this->data;
        $opsJobItem = $this->opsJobItem;

        if ($opsJobItem->customer && $opsJobItem->customer->person_id) {
            $data['customers'][$opsJobItem->customer->person_id] = [
                'attachments' => [],
                'cash_collected' => $opsJobItem->cash_amount ? $opsJobItem->cash_amount : 0,
                'channels' => [],
                'ops_job_item_id' => $opsJobItem->id,
                'sequence' => $opsJobItem->sequence,
            ];

            if ($opsJobItem->opsJobItemChannels) {
                foreach ($opsJobItem->opsJobItemChannels as $opsJobItemChannel) {
                    if ($opsJobItemChannel->actual_qty > 0) {
                        if (!$opsJobItemChannel->vendChannel->product || !$opsJobItemChannel->vendChannel->product->code) {
                            throw new \Exception('Product code is missing, please check bindings: ' . $opsJobItemChannel->vend_channel_code);
                        }

                        $data['customers'][$opsJobItem->customer->person_id]['channels'][$opsJobItemChannel->vend_channel_code] = [
                            'amount' => $opsJobItemChannel->actual_qty * $opsJobItemChannel->vendChannel->amount,
                            'unit_price' => $opsJobItemChannel->vendChannel->amount,
                            'product_code' => $opsJobItemChannel->vendChannel->product->code,
                            'capacity' => $opsJobItemChannel->capacity,
                            'qty' => $opsJobItemChannel->vendChannel->qty,
                            'needed' => $opsJobItemChannel->actual_qty,
                        ];
                    }
                }
            }

            if ($opsJobItem->attachments) {
                foreach ($opsJobItem->attachments as $attachment) {
                    $data['customers'][$opsJobItem->customer->person_id]['attachments'][$attachment->id] = [
                        'created_at' => $attachment->created_at,
                        'name' => $attachment->name,
                        'sequence' => $attachment->sequence,
                        'url' => $attachment->full_url,
                    ];
                }
            }

            $opsJobItem->update([
                'cms_transaction_by' => $this->userID,
            ]);
        }

        $response = Http::post($this->endpoint, $data);

        if ($response->successful()) {
            $this->opsJobService->updateJobItemCMSTransactionID($response->json());
        }
    }
}
