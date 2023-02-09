<?php

namespace App\Jobs\Vend;

use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Jobs\Vend\SyncVendChannelErrorLog;
use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateVendTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $input;
    protected $vend;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($input, Vend $vend)
    {
        $this->input = $input;
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $input = $this->input;
        $vend = $this->vend;

        $paymentMethod = PaymentMethod::where('code', $input['PAY_TYPE'])->first();

        if($sID = $input['SId']) {
            $vendChannel = VendChannel::where('code', $sID)->where('vend_id', $vend->id)->first();

            if(!$vendChannel) {
                $vendChannel = VendChannel::create([
                    'code' => $sID,
                    'vend_id' => $vend->id,
                ]);
            }
        }

        $vendChannelError = VendChannelError::where('code', (isset($input['SErr']) ? $input['SErr'] : 0))->where('code', '!=', 0)->first();

        $productId = null;
        if(isset($vendChannel) and $vendChannel and $vend->productMapping()->exists()) {
            $productMappingItem = $vend->productMapping->productMappingItems()->where('channel_code', $vendChannel->code)->first();
            if($productMappingItem) {
                $productId = $productMappingItem->product_id;
            }
        }

        $vendTransaction = VendTransaction::create(
            [
            'transaction_datetime' => Carbon::now(),
            'amount' => $input['Price'],
            'order_id' => $input['ORDRID'],
            'payment_method_id' => $paymentMethod ? $paymentMethod->id : null,
            'vend_id' => $vend->id,
            'vend_channel_id' => isset($vendChannel) ? $vendChannel->id : 0,
            'vend_channel_error_id' => isset($vendChannelError) ? $vendChannelError->id : null,
            'vend_transaction_json' => $input,
            'product_id' => $productId,
            ]
        );

        SyncVendTransactionTotalsJson::dispatch($vendTransaction->vend)->onQueue('default');

        if($vendChannelError) {
            SyncVendChannelErrorLog::dispatch($vend, $input['SId'], $input['SErr'], $vendTransaction->id)->onQueue('default');
        }
    }
}
