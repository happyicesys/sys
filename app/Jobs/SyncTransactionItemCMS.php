<?php

namespace App\Jobs;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SyncTransactionItemCMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $customerID;
    protected $endpoint;
    /**
     * Create a new job instance.
     */
    public function __construct($customerID)
    {
        $this->customerID = $customerID;
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

        $customer = Customer::with(['vend.vendChannels.product'])->find($this->customerID);

        if (!$customer) {
            // Customer no longer exists (may have been deleted), skip silently
            return;
        }

        if ($customer->cms_invoice_history and isset($customer->cms_invoice_history['next_transaction_id'])) {
            $data = [
                'status' => 'Confirmed',
                'cms_person_id' => isset($customer->cms_invoice_history['id']) ? $customer->cms_invoice_history['id'] : null,
                'transaction_id' => $customer->cms_invoice_history['next_transaction_id'],
                'items' => [],
            ];

            if ($customer->vend and $customer->vend->vendChannels) {
                foreach ($customer->vend->vendChannels as $vendChannel) {
                    if ($vendChannel->capacity > 0 and $vendChannel->is_active and $vendChannel->product and $vendChannel->product->is_available) {
                        $data['items'][$vendChannel->code] = [
                            'product_code' => $vendChannel->product->code,
                            'capacity' => $vendChannel->capacity,
                            'qty' => $vendChannel->qty,
                            'needed' => $vendChannel->capacity - $vendChannel->qty,
                        ];
                    }
                }
            }

            $response = Http::post($this->endpoint, $data);
        }
    }
}
