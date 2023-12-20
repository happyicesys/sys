<?php

namespace App\Jobs\Vend;

use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\PaymentGateways\Midtrans;
use App\Models\PaymentGateways\Omise;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
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

        $processedInput = $this->processMapping($this->processInput($input));

        // check duplicated orderid
        $duplicatedVendTransaction = VendTransaction::where('order_id', $processedInput['orderID'])->where('vend_id', $vend->id)->first();

        // exit once found duplicated order id
        if($duplicatedVendTransaction) {
            return;
        }
        // dd($processedInput);

        $vendTransaction = $this->createVendTransaction($processedInput);

        if($processedInput['isMultiple']) {
            foreach($processedInput['children'] as $child) {
                $this->createVendTransactionItem($vendTransaction, $child);
            }
            $vendTransaction->update([
                'vend_transaction_items_json' => $vendTransaction->vendTransactionItems()->with(['product', 'vendChannelError'])->get(),
            ]);
        }

        // store vend transaction id if found delivery platform order
        if($deliveryPlatformOrder = DeliveryPlatformOrder::where('vend_transaction_order_id', $processedInput['orderID'])->first()) {
            $deliveryPlatformOrder->update([
                'vend_transaction_id' => $vendTransaction->id,
            ]);
        }

        if(!$processedInput['isSuccessful']) {
            HandleFailedVendTransaction::dispatch($vendTransaction)->onQueue('default');
        }

        SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('default');
        SyncUnitCostJson::dispatch($vendTransaction)->onQueue('default');

        if($processedInput['vendChannelErrorID']) {
            SyncVendChannelErrorLog::dispatch($vend, $processedInput['vendChannelCode'], $processedInput['errorCode'], $vendTransaction->id)->onQueue('default');
        }
    }

    private function createVendTransaction($input)
    {
        $vendTransaction = VendTransaction::create([
            'transaction_datetime' => $this->isCurrentTime ? Carbon::now() : Carbon::parse($input['time']),
            'amount' => $input['amount'],
            'order_id' => $input['orderID'],
            'is_multiple' => $input['isMultiple'],
            'is_payment_received' => $input['isPaymentReceived'],
            'items_json' => $input['children'],
            'payment_method_id' => $input['paymentMethodID'],
            'vend_id' => $this->vend->id,
            'vend_channel_code' => $input['vendChannelCode'],
            'vend_channel_id' => $input['vendChannelID'],
            'vend_channel_error_id' => $input['vendChannelErrorID'],
            'vend_transaction_json' => $input['originalJson'],
            'payment_gateway_log_id' => $input['paymentGatewayLogID'],
            'product_id' => $input['productID'],
            'vend_json' => $this->vend ? collect($this->vend)->except(['latest_vend_binding', 'product_mapping', 'vend_channels_json']) : null,
            'customer_id' => $this->vend->latestVendBinding()->exists() && $this->vend->latestVendBinding->customer()->exists() ? $this->vend->latestVendBinding->customer->id : null,
            'customer_json' => $this->vend->latestVendBinding()->exists() && $this->vend->latestVendBinding->customer()->exists() ? collect($this->vend->latestVendBinding->customer()->with([
                'category.categoryGroup'
            ])->first()) : [
                'name' => $this->vend->name,
            ],
            'location_type_json' => $this->vend->latestVendBinding()->exists() && $this->vend->latestVendBinding->customer()->exists() && $this->vend->latestVendBinding->customer->locationType()->exists() ? collect($this->vend->latestVendBinding->customer->locationType) : null,
            'operator_id' => $this->vend->currentOperator()->exists() ? $this->vend->currentOperator->first()->id : null,
            'operator_json' => $this->vend->currentOperator()->exists() ? collect($this->vend->currentOperator->first()) : null,
            'product_json' => $input['productID'] ? collect($this->vend->productMapping->productMappingItems()->where('channel_code', $input['vendChannelCode'])->first()->product)->except(['product_mapping_items']) : null,
            'unit_cost_id' => $input['unitCostID'],
            'gst_vat_rate' => $input['gstVatRate'],
        ]);

        return $vendTransaction;
    }

    private function createVendTransactionItem($vendTransaction, $input)
    {
        $vendTransactionItem = VendTransactionItem::create([
            'is_refunded' => false,
            'product_id' => $input['productID'],
            'product_json' => $input['productID'] ? collect($this->vend->productMapping->productMappingItems()->where('channel_code', $input['vendChannelCode'])->first()->product)->except(['product_mapping_items']) : null,
            'unit_cost_id' => $input['unitCostID'],
            'vend_channel_id' => $input['vendChannelID'],
            'vend_channel_code' => $input['vendChannelCode'],
            'vend_channel_error_id' => $input['vendChannelErrorID'],
            'vend_transaction_id' => $vendTransaction->id,
        ]);

        return $vendTransactionItem;
    }

    private function createVendChannel($vendID, $channelCode)
    {
        $vendChannel = VendChannel::create([
            'code' => $channelCode,
            'vend_id' => $vendID,
        ]);

        return $vendChannel;
    }

    private function processMapping($input)
    {
        $gstVatRate = 0;
        $isPaymentReceived = false;
        $isSuccessful = false;
        $paymentMethod = isset($input['paymentMethodCode']) ? PaymentMethod::where('code', $input['paymentMethodCode'])->first() : null;
        $product = null;
        $unitCost = null;
        $unitCostId = null;
        $vendChannel = VendChannel::where('code', $input['vendChannelCode'])->where('code', '!=', 0)->where('vend_id', $this->vend->id)->first();
        $vendChannelError = VendChannelError::where('code', $input['errorCode'])->where('code', '!=', 0)->first();

        // hardcode when 0 and 6 error code means successful dispense
        if($input['errorCode'] == '0' or $input['errorCode'] == '6') {
            $isPaymentReceived = true;
            $isSuccessful = true;
        }

        // handle those QR payment and grab mart, treat as payment received by default
        if($paymentMethod) {
            if(isset(Midtrans::PAYMENT_METHOD_MAPPING[$paymentMethod->code]) or isset(Omise::PAYMENT_METHOD_MAPPING[$paymentMethod->code]) or Grab::PAYMENT_METHOD_GRABMART == $paymentMethod->code) {
                $isPaymentReceived = true;
            }
        }

        // check is from payment gateway log
        if(isset($input['orderID'])) {
            $paymentGatewayLog = PaymentGatewayLog::where('order_id', $input['orderID'])->where('status', PaymentGatewayLog::STATUS_APPROVE)->first();
        }

        // mapping product ID, and find unit cost, gst rate
        if(isset($vendChannel) and $vendChannel and $this->vend->productMapping()->exists()) {
            $productMappingItem = $this->vend->productMapping->productMappingItems()->where('channel_code', $vendChannel->code)->first();
            if($productMappingItem) {
                $product = $productMappingItem->product;
                $unitCost = $product->unitCosts()->where('is_current', true)->first();
                $gstVatRate = $product->operator ? $product->operator->gst_vat_rate : 0;
            }
        }

        // handle not found vend channel
        if(!$vendChannel and $input['vendChannelCode'] != 0){
            $vendChannel = $this->createVendChannel($this->vend->id, $input['vendChannelCode']);
        }

        return [
            'amount' => isset($input['amount']) ? $input['amount'] : 0,
            'children' => isset($input['children']) ? $input['children'] : [],
            'errorCode' => $input['errorCode'],
            'gstVatRate' => $gstVatRate,
            'isMultiple' => isset($input['isMultiple']) ? $input['isMultiple'] : false,
            'isPaymentReceived' => $isPaymentReceived,
            'isSuccessful' => $isSuccessful,
            'orderID' => isset($input['orderID']) ? $input['orderID'] : null,
            'originalJson' => isset($input['originalJson']) ? $input['originalJson'] : null,
            'paymentGatewayLogID' => isset($paymentGatewayLog) ? $paymentGatewayLog->id : null,
            'paymentMethodCode' => isset($input['paymentMethodCode']) ? $input['paymentMethodCode'] : null,
            'paymentMethodID' => $paymentMethod ? $paymentMethod->id : null,
            'productID' => $product ? $product->id : null,
            'time' => isset($input['time']) ? $input['time'] : null,
            'unitCostID' => $unitCost ? $unitCost->id : null,
            'vendChannelCode' => $input['vendChannelCode'],
            'vendChannelError' => $vendChannelError,
            'vendChannelErrorID' => $vendChannelError ? $vendChannelError->id : null,
            'vendChannelID' => $vendChannel ? $vendChannel->id : 0,
        ];
    }

    private function processInput($input)
    {
        $data = [];

        $data['originalJson'] = $input;
        $data['amount'] = isset($input['Price']) ? (isset($input['transf_info']) ? ($input['Price'] * 100) : $input['Price']) : 0;
        $data['orderID'] = isset($input['ORDRID']) ? $input['ORDRID'] : null;
        $data['paymentMethodCode'] = isset($input['PAY_TYPE']) ? $input['PAY_TYPE'] : null;
        $data['time'] = isset($input['TIME']) ? $input['TIME'] : Carbon::now()->toDateTimeString();
        $data['errorCode'] = isset($input['SErr']) ? $input['SErr'] : (isset($input['errorCode']) ? $input['errorCode'] : 0);
        $data['vendChannelCode'] = isset($input['SId']) ? $input['SId'] : 0;
        $data['isMultiple'] = false;
        $data['children'] = [];

        if(isset($input['transf_info']) and sizeof($input['transf_info']) == 1) {
            $data['isMultiple'] = false;
            $data['errorCode'] = $input['transf_info']['SErr'];
            $data['vendChannelCode'] = $input['transf_info']['SId'];
        }

        if(isset($input['transf_info']) and sizeof($input['transf_info']) > 1) {
            $data['isMultiple'] = true;
            foreach($input['transf_info'] as $trans) {
                // $data['children'][] = [
                //     'errorCode' => $trans['SErr'],
                //     'vendChannelCode' => $trans['SId'],
                // ];
                $data['children'][] = $this->processMapping([
                    'errorCode' => $trans['SErr'],
                    'vendChannelCode' => $trans['SId'],
                ]);
            }
        }

        return $data;
    }
}
