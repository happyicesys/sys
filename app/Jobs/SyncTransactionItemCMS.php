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
        $this->endpoint = env('CMS_URL') . '/api/transactions/deals';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $customer = Customer::findOrFail($this->customerID);

        if($customer->cms_invoice_history and isset($customer->cms_invoice_history['next_transaction_id'])) {
            $data = [
                'cms_person_id' => isset($customer->cms_invoice_history['id']) ? $customer->cms_invoice_history['id'] : null,
                'transaction_id' => $customer->cms_invoice_history['next_transaction_id'],
                'items' => [],
            ];

            if($customer->vend and $customer->vend->vendChannels) {
                foreach($customer->vend->vendChannels as $vendChannel) {
                    if($vendChannel->capacity > 0 and $vendChannel->is_active) {
                        $data['items'][$vendChannel->code] = [
                            'product_code' => $vendChannel->product ? $vendChannel->product->code : null,
                            'capacity' => $vendChannel->capacity,
                            'qty' => $vendChannel->qty,
                            'needed' => $vendChannel->capacity - $vendChannel->qty,
                        ];
                    }
                }
            }

            // dd($data);

            Http::post($this->endpoint, $data);
        }
    }
}
