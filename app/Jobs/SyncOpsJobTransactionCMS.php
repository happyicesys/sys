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
use Illuminate\Support\Facades\Log;

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

        // Eager-load all relations accessed in the loop to prevent N+1 queries
        $opsJobItem->loadMissing([
            'customer',
            'opsJobItemChannels.vendChannel',
            'opsJobItemChannels.product',
            // Blind SKU: frozen flavour set, to deduct flavours (not the housing) in CMS.
            'opsJobItemChannels.children.childProduct:id,code,is_available',
            'attachments',
        ]);

        if ($opsJobItem->customer && $opsJobItem->customer->person_id) {
            $data['customers'][$opsJobItem->customer->person_id] = [
                'attachments' => [],
                'cash_collected' => $opsJobItem->cash_amount ? $opsJobItem->cash_amount : 0,
                'channels' => [],
                'ops_job_item_id' => $opsJobItem->id,
                'sequence' => $opsJobItem->sequence,
            ];

            if ($opsJobItem->opsJobItemChannels) {
                $personId = $opsJobItem->customer->person_id;

                foreach ($opsJobItem->opsJobItemChannels as $opsJobItemChannel) {
                    if ($opsJobItemChannel->actual_qty == 0) {
                        continue;
                    }

                    $unitPrice = $opsJobItemChannel->vendChannel->amount;
                    $channelCode = $opsJobItemChannel->vend_channel_code;

                    // Blind SKU: a housing channel must deduct its FLAVOURS in CMS,
                    // not the parent (CMS has no stock for the housing). Split the
                    // stock-in qty across the frozen flavour set by weight — same
                    // logic/result as the OpsJob screen — and emit one line per flavour.
                    $ledger = $opsJobItemChannel->children;
                    if ($ledger && $ledger->isNotEmpty()) {
                        $allocInput = $ledger->map(fn ($c) => [
                            'key' => (int) $c->child_product_id,
                            'weight' => (int) $c->weight_pct,
                            'available' => (bool) (optional($c->childProduct)->is_available ?? true),
                            'cap' => null,
                            'sort' => (int) $c->sort,
                        ])->all();

                        $alloc = \App\Services\BlindSkuService::allocateToPick(
                            (int) $opsJobItemChannel->actual_qty,
                            $allocInput
                        );

                        $emitted = 0;
                        foreach ($ledger as $c) {
                            $childQty = $alloc[(int) $c->child_product_id] ?? 0;
                            $childCode = optional($c->childProduct)->code;
                            if ($childQty <= 0 || !$childCode) {
                                continue;
                            }
                            $data['customers'][$personId]['channels'][$channelCode . '_' . $childCode] = [
                                'amount' => $childQty * $unitPrice,
                                'unit_price' => $unitPrice,
                                'product_code' => $childCode,
                                'capacity' => $opsJobItemChannel->capacity,
                                'qty' => $opsJobItemChannel->vendChannel->qty,
                                'needed' => $childQty,
                            ];
                            $emitted += $childQty;
                        }

                        // If at least one flavour absorbed the stock-in, the housing
                        // line is intentionally omitted. Only fall through to the parent
                        // line as a safety net when NOTHING could be allocated.
                        if ($emitted > 0) {
                            continue;
                        }
                    }

                    // Normal product (or blind safety fallback): emit the channel's own line.
                    $product = $opsJobItemChannel->product ?? $opsJobItemChannel->vendChannel->product;

                    if (!$product || !$product->code) {
                        Log::warning('Product code is missing, skipped bindings: ' . $opsJobItemChannel->vend_channel_code, ['ops_job_item_id' => $opsJobItem->id]);
                        continue;
                    }

                    $data['customers'][$personId]['channels'][$channelCode . '_' . $product->code] = [
                        'amount' => $opsJobItemChannel->actual_qty * $unitPrice,
                        'unit_price' => $unitPrice,
                        'product_code' => $product->code,
                        'capacity' => $opsJobItemChannel->capacity,
                        'qty' => $opsJobItemChannel->vendChannel->qty,
                        'needed' => $opsJobItemChannel->actual_qty,
                    ];
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

        Log::info('SyncOpsJobTransactionCMS Request:', [
            'endpoint' => $this->endpoint,
            'data' => $data
        ]);

        $response = Http::post($this->endpoint, $data);

        Log::info('SyncOpsJobTransactionCMS Response:', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        if ($response->successful()) {
            $this->opsJobService->updateJobItemCMSTransactionID($response->json());
        }
    }
}
