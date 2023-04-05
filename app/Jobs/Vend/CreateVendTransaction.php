<?php

namespace App\Jobs\Vend;

use App\Models\PaymentGateway\Midtrans;
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
        $isPaymentReceived = false;

        $input = $this->input;
        $vend = $this->vend;

        $processedInput = $this->processInput($input);

        $paymentMethod = PaymentMethod::where('code', $processedInput['paymentMethodCode'])->first();
        $vendChannel = VendChannel::where('code', $processedInput['channelCode'])->where('vend_id', $vend->id)->first();

        if(!$vendChannel) {
            $vendChannel = VendChannel::create([
                'code' => $processedInput['channelCode'],
                'vend_id' => $vend->id,
            ]);
        }

        $vendChannelError = VendChannelError::where('code', $processedInput['errorCode'])->where('code', '!=', 0)->first();

        if($processedInput['errorCode'] == 0 or $processedInput['errorCode'] == '6') {
            $isPaymentReceived = true;
        }

        if($paymentMethod) {
            if(
                $paymentMethod->code == Midtrans::PAYMENT_METHOD_GOPAY or
                $paymentMethod->code == Midtrans::PAYMENT_METHOD_AIRPAY_SHOPEE or
                $paymentMethod->code == Midtrans::PAYMENT_METHOD_DANA or
                $paymentMethod->code == Midtrans::PAYMENT_METHOD_OVO or
                $paymentMethod->code == Midtrans::PAYMENT_METHOD_TCASH
            ) {
                $isPaymentReceived = true;
            }
        }

        $productId = null;
        if(isset($vendChannel) and $vendChannel and $vend->productMapping()->exists()) {
            $productMappingItem = $vend->productMapping->productMappingItems()->where('channel_code', $vendChannel->code)->first();
            if($productMappingItem) {
                $productId = $productMappingItem->product_id;
            }
        }

        // check duplicated orderid
        $duplicatedOrderId = VendTransaction::where('order_id', $processedInput['orderId'])->where('vend_id', $vend->id)->first();

        if($duplicatedOrderId) {
            return;
        }

        $vendTransaction = VendTransaction::create([
            'transaction_datetime' => Carbon::now(),
            'amount' => $processedInput['amount'],
            'order_id' => $processedInput['orderId'],
            'is_payment_received' => $isPaymentReceived,
            'payment_method_id' => $paymentMethod ? $paymentMethod->id : null,
            'vend_id' => $vend->id,
            'vend_channel_id' => isset($vendChannel) ? $vendChannel->id : 0,
            'vend_channel_error_id' => isset($vendChannelError) ? $vendChannelError->id : null,
            'vend_transaction_json' => $input,
            'product_id' => $productId,
            'vend_json' => $vend->latestVendBinding && $vend->latestVendBinding->customer ? collect($vend)->except(['vend_channels_json', 'product_mapping']) : null,
        ]);

        SyncVendTransactionTotalsJson::dispatch($vendTransaction->vend)->onQueue('default');

        if($vendChannelError) {
            SyncVendChannelErrorLog::dispatch($vend, $processedInput['channelCode'], $processedInput['errorCode'], $vendTransaction->id)->onQueue('default');
        }
    }

    private function processInput($input)
    {
        $amount = $input['Price'];
        $errorCode = 0;
        $orderId = $input['ORDRID'];
        $paymentMethodCode = $input['PAY_TYPE'];
        $channelCode = 0;

        if(isset($input['transf_info']) and isset($input['transf_info']) and isset($input['fail_info'])) {
            if(sizeof($input['fail_info']) > 0) {
                $errorCode = $input['fail_info']['SErr'];
            }
            if(sizeof($input['transf_info']) > 0) {
                $channelCode = $input['transf_info']['SId'];
            }
        }else {
            $errorCode = $input['SErr'];
            $channelCode = $input['SId'];
        }

        return [
            'amount' => $amount,
            'errorCode' => $errorCode,
            'orderId' => $orderId,
            'paymentMethodCode' => $paymentMethodCode,
            'channelCode' => $channelCode,
        ];
    }
}
