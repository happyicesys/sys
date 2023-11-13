<?php

namespace App\Jobs\Vend;

use App\Models\DeliveryPlatformOrder;
use App\Models\PaymentGateways\Midtrans;
use App\Models\PaymentGateways\Omise;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Jobs\HandleFailedVendTransaction;
use App\Jobs\Vend\SyncUnitCostJson;
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
    protected $isCurrentTime;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($input, Vend $vend, $isCurrentTime = true)
    {
        $this->input = $input;
        $this->vend = $vend;
        $this->isCurrentTime = $isCurrentTime;
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

        $isPaymentReceived = false;
        $isSuccessful = false;
        if($processedInput['errorCode'] == '0' or $processedInput['errorCode'] == '6') {
            $isPaymentReceived = true;
            $isSuccessful = true;
        }

        if($paymentMethod) {
            if(
                isset(Midtrans::PAYMENT_METHOD_MAPPING[$paymentMethod->code]) or isset(Omise::PAYMENT_METHOD_MAPPING[$paymentMethod->code])
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

        $unitCostId = null;
        $gstVatRate = 0;
        if($productId) {
            $product = Product::find($productId);
            $unitCostId = $product->unitCosts()->where('is_current', true)->first() ? $product->unitCosts()->where('is_current', true)->first()->id : null;
            $gstVatRate = $product->operator ? $product->operator->gst_vat_rate : 0;
        }

        // check duplicated orderid
        $duplicatedOrderId = VendTransaction::where('order_id', $processedInput['orderId'])->where('vend_id', $vend->id)->first();

        // exit once found duplicated order id
        if($duplicatedOrderId) {
            return;
        }

        // check is from payment gateway log
        $paymentGatewayLog = PaymentGatewayLog::where('order_id', $processedInput['orderId'])->where('status', PaymentGatewayLog::STATUS_APPROVE)->first();

        // check is from delivery platform order
        $deliveryPlatformOrder = DeliveryPlatformOrder::where('vend_transaction_order_id', $processedInput['orderId'])->first();

        $vendTransaction = VendTransaction::create([
            'transaction_datetime' => $this->isCurrentTime ? Carbon::now() : Carbon::parse($input['TIME']),
            'amount' => $processedInput['amount'],
            'order_id' => $processedInput['orderId'],
            'is_payment_received' => $isPaymentReceived,
            'payment_method_id' => $paymentMethod ? $paymentMethod->id : null,
            'vend_id' => $vend->id,
            'vend_channel_code' => isset($vendChannel) ? $vendChannel->code : null,
            'vend_channel_id' => isset($vendChannel) ? $vendChannel->id : 0,
            'vend_channel_error_id' => isset($vendChannelError) ? $vendChannelError->id : null,
            'vend_transaction_json' => $input,
            'payment_gateway_log_id' => $paymentGatewayLog ? $paymentGatewayLog->id : null,
            'product_id' => $productId,
            'vend_json' => $vend ? collect($vend)->except(['latest_vend_binding', 'product_mapping', 'vend_channels_json']) : null,
            'customer_id' => $vend->latestVendBinding()->exists() && $vend->latestVendBinding->customer()->exists() ? $vend->latestVendBinding->customer->id : null,
            'customer_json' => $vend->latestVendBinding()->exists() && $vend->latestVendBinding->customer()->exists() ? collect($vend->latestVendBinding->customer()->with([
                'category.categoryGroup'
            ])->first()) : [
                'name' => $vend->name,
            ],
            'location_type_json' => $vend->latestVendBinding()->exists() && $vend->latestVendBinding->customer()->exists() && $vend->latestVendBinding->customer->locationType()->exists() ? collect($vend->latestVendBinding->customer->locationType) : null,
            'operator_id' => $vend->currentOperator()->exists() ? $vend->currentOperator->first()->id : null,
            'operator_json' => $vend->currentOperator()->exists() ? collect($vend->currentOperator->first()) : null,
            'product_json' => $productId ? collect($vend->productMapping->productMappingItems()->where('channel_code', $vendChannel->code)->first()->product)->except(['product_mapping_items']) : null,
            'unit_cost_id' => $unitCostId,
            'gst_vat_rate' => $gstVatRate,
        ]);

        // store vend transaction id if found delivery platform order
        if($deliveryPlatformOrder) {
            $deliveryPlatformOrder->update([
                'vend_transaction_id' => $vendTransaction->id,
            ]);
        }

        if(!$isSuccessful) {
            HandleFailedVendTransaction::dispatch($vendTransaction)->onQueue('default');
        }

        SyncVendTransactionTotalsJson::dispatch($vendTransaction->vend)->onQueue('default');
        SyncUnitCostJson::dispatch($vendTransaction)->onQueue('default');

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
