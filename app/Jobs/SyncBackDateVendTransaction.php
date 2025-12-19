<?php

namespace App\Jobs;

use App\Models\VendTransaction;
use App\Jobs\Vend\SyncUnitCostJson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncBackDateVendTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $input;
    protected $vend;
    /**
     * Create a new job instance.
     */
    public function __construct($vend, $input)
    {
        $this->input = $input;
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $input = $this->input;

        $vendTransaction = VendTransaction::create([
            'transaction_datetime' => $input['date'],
            'amount' => $input['amount'],
            'order_id' => $input['orderID'],
            'interface_type' => null,
            'is_multiple' => 0,
            'is_payment_received' => 1,
            'items_json' => [],
            'payment_method_id' => $input['paymentMethodID'],
            'vend_id' => $this->vend->id,
            'vend_channel_code' => 0,
            'vend_channel_id' => 0,
            'vend_channel_error_id' => null,
            'vend_transaction_json' => null,
            'payment_gateway_log_id' => null,
            'product_id' => null,
            'customer_id' => $this->vend->customer()->exists() ? $this->vend->customer->id : null,
            'location_type_id' => $this->vend->customer()->exists() && $this->vend->customer->locationType()->exists() ? $this->vend->customer->locationType->id : null,
            'operator_id' => $this->vend->customer()->exists() && $this->vend->customer->operator()->exists() ? $this->vend->customer->operator->id : 1,
            'unit_cost_id' => null,
            'gst_vat_rate' => $this->vend->customer()->exists() && $this->vend->customer->operator()->exists() ? $this->vend->customer->operator->gst_vat_rate : 0,
            'meta_json' => [
                'vend_code' => $this->vend->code,
                'customer_code' => $this->vend->customer()->exists() ? $this->vend->customer->id + 20000 : null,
                'customer_name' => $this->vend->customer()->exists() ? $this->vend->customer->name : null,
                'vend_prefix_id' => $this->vend->vendPrefix()->exists() ? $this->vend->vendPrefix->id : null,
                'vend_prefix_name' => $this->vend->vendPrefix()->exists() ? $this->vend->vendPrefix->name : null,
            ],
            'is_zero_amount' => $input['amount'] == 0,
        ]);

        SyncUnitCostJson::dispatch($vendTransaction)->onQueue('default');
    }
}
