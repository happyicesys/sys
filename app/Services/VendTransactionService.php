<?php

namespace App\Services;
use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\PaymentGateways\Midtrans;
use App\Models\PaymentGateways\Omise;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\UnitCost;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendData;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Jobs\CreateVendData;
use App\Jobs\HandleFailedVendTransaction;
use App\Jobs\SendDataToDcvend;
use App\Jobs\Vend\SyncUnitCostJson;
use App\Jobs\Vend\SyncVendChannelErrorLog;
use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Services\VoucherService;
use Carbon\Carbon;
use DB;

class VendTransactionService
{
    protected $voucherService;

    public function __construct()
    {
        $this->voucherService = new VoucherService();
    }


    public function create(Vend $vend, $input, $isCurrentTime = true)
    {
        $vend->loadMissing([
            'customer.locationType',
            'customer.operator',
            'vendPrefix',
            'productMapping.productMappingItems.product.unitCosts.operator',
        ]);

        $processedInput = $this->processMapping($vend, $this->processInput($vend, $input));

        DB::statement("SET innodb_lock_wait_timeout = 5"); // Prevent long waits
        DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');

        try {
            // 🔥 Store the result of the transaction
            $vendTransaction = DB::transaction(function () use ($processedInput, $vend, $input, $isCurrentTime) {
                if ($processedInput['interfaceType'] == '50') {
                    $processedInput['orderID'] = Carbon::now()->format('y') . (Carbon::now()->format('m'))[0] . $processedInput['orderID'];
                }

                if ($vend->code != '2007') {
                    $duplicatedVendTransaction = VendTransaction::query()
                        ->where(function ($query) use ($processedInput) {
                            $query->where('order_id', $processedInput['orderID'])
                                ->orWhere('order_id', Carbon::now()->format('y') . (Carbon::now()->format('m'))[0] . $processedInput['orderID']);
                        })
                        ->where('vend_id', $vend->id)
                        ->lockForUpdate()
                        ->first();

                    if ($duplicatedVendTransaction) {
                        CreateVendData::dispatch(
                            $input,                                      // originalInput
                            $duplicatedVendTransaction->vend_transaction_json, // processedInput
                            null,                                        // ipAddress
                            'vend-transaction',                          // connectionType
                            'duplicated_order_id',                       // type
                            true                                         // isKeep
                        )->onQueue('default');

                        return null; // Exit and return null if duplicate exists
                    }

                    $shortVersionCreatedBefore = VendTransaction::query()
                        ->where('order_id', substr($processedInput['orderID'], 2))
                        ->where('vend_id', $vend->id)
                        ->lockForUpdate()
                        ->first();

                    if ($shortVersionCreatedBefore) {
                        $shortVersionCreatedBefore->delete();
                    }
                }

                // ✅ Create and return vend transaction

                $transaction = $this->createVendTransaction($vend, $processedInput, $isCurrentTime);

                if($processedInput['vouchers']) {
                    foreach($processedInput['vouchers'] as $voucher) {
                        $this->voucherService->updateUsedVoucher($voucher['code']);
                    }
                }

                return $transaction;
            }, 3); // Retry up to 3 times

            if (!$vendTransaction) {
                return; // Prevent further execution if duplicate order ID
            }

            // store vend transaction id if found delivery platform order
            if($deliveryPlatformOrder = DeliveryPlatformOrder::where('vend_transaction_order_id', $processedInput['orderID'])->first()) {
                $deliveryPlatformOrder->update([
                    'vend_transaction_id' => $vendTransaction->id,
                    'status' => DeliveryPlatformOrder::STATUS_DISPENSED > $deliveryPlatformOrder->status ? DeliveryPlatformOrder::STATUS_DISPENSED : $deliveryPlatformOrder->status,
                    'status_json' => array_merge_recursive($deliveryPlatformOrder->status_json, [
                        'status' => DeliveryPlatformOrder::STATUS_MAPPING[DeliveryPlatformOrder::STATUS_DISPENSED],
                        'datetime' => Carbon::now()->toDateTimeString(),
                    ]),
                ]);
            }

            if($paymentGatewayLog = PaymentGatewayLog::where('order_id', $vendTransaction->order_id)->first()) {
                $vendTransaction->update([
                    'payment_gateway_log_id' => $paymentGatewayLog->id,
                ]);
            }

            // if($deliveryPlatformOrder = DeliveryPlatformOrder::where('vend_transaction_order_id', $processedInput['orderID'])->first()) {
            //     $deliveryPlatformOrder->update([
            //         'vend_transaction_id' => $vendTransaction->id,
            //     ]);
            // }

        } catch (\Exception $e) {
            if ($e->getCode() == 1205) { // MySQL Lock Timeout error
                $this->release(5); // Retry after 5 seconds
            }
            \Log::error("Error creating vend transaction: " . $e->getMessage());
            return;
        }

        // ✅ Use $vendTransaction safely outside the transaction
        if (!$processedInput['isSuccessful']) {
            HandleFailedVendTransaction::dispatch($vendTransaction)->onQueue('default');
        }

        SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('default');

        if ($vendTransaction) {
// dd(sizeof($processedInput['children']), $processedInput['children']);
            if(sizeof($processedInput['children']) > 1) {
                foreach ($processedInput['children'] as $child) {
                    $this->createVendTransactionItem($vendTransaction, $child);
                }
            }

            SyncUnitCostJson::dispatch($vendTransaction)->onQueue('default');
        }

        if ($processedInput['vendChannelErrorID']) {
            SyncVendChannelErrorLog::dispatch($vend, $processedInput['vendChannelCode'], $processedInput['errorCode'], $vendTransaction->id)->onQueue('default');
        }

        if ($processedInput['dcvendUserID']) {
            SendDataToDcvend::dispatch($vendTransaction->id, $processedInput['dcvendUserID'])->onQueue('default');
        }
    }

    private function createVendTransaction($vend, $input, $isCurrentTime)
    {
        $customer = $vend->customer;
        $vendPrefix = $vend->vendPrefix;

        $vendTransaction = VendTransaction::create([
            'transaction_datetime' => $isCurrentTime ? Carbon::now() : Carbon::parse($input['time']),
            'amount' => $input['amount'],
            'order_id' => $input['orderID'],
            'interface_type' => $input['interfaceType'],
            'is_multiple' => $input['isMultiple'],
            'is_payment_received' => $input['isPaymentReceived'],
            'items_json' => $input['children'],
            'payment_method_id' => $input['paymentMethodID'],
            'qty' => $input['qty'],
            'success_qty' => $input['success_qty'],
            'vend_id' => $vend->id,
            'vend_channel_code' => $input['vendChannelCode'],
            'vend_channel_id' => $input['vendChannelID'],
            'vend_channel_error_id' => $input['vendChannelErrorID'],
            'vend_transaction_json' => $input['originalJson'],
            // 'payment_gateway_log_id' => $input['paymentGatewayLogID'],
            'product_id' => $input['productID'],
            'customer_id' => $customer?->id ?? null,
            'location_type_id' => $customer?->locationType?->id ?? null,
            'operator_id' => $customer?->operator?->id ?? 1,
            'unit_cost_id' => $input['unitCostID'],
            'gst_vat_rate' => $input['gstVatRate'],
            'meta_json' => [
                'apk_ver' => isset($vend->apk_ver_json['apkver']) ? $vend->apk_ver_json['apkver'] : null,
                'firmware_ver' => isset($vend->firmware_ver) ? dechex($vend->firmware_ver) : null,
                'vend_code' => $vend->code,
                'customer_code' => $customer?->id + 20000 ?? null,
                'customer_name' => $customer?->name ?? null,
                'vend_prefix_id' => $vendPrefix?->id ?? null,
                'vend_prefix_name' => $vendPrefix?->name ?? null,
                'vouchers' => $input['vouchers'],
                'hid_card_id' => $input['hid_card_id'] ?? null,
            ]
        ]);

        return $vendTransaction;
    }

    private function createVendTransactionItem($vendTransaction, $input)
    {
        $vendTransactionItem = VendTransactionItem::create([
            'is_refunded' => false,
            'product_id' => $input['productID'],
            'unit_cost_id' => $input['unitCostID'],
            'unit_cost' => isset($input['unitCostID']) && $input['unitCostID'] ? UnitCost::find($input['unitCostID'])->cost : 0,
            'vend_channel_id' => $input['vendChannelID'],
            'vend_channel_code' => $input['vendChannelCode'],
            'vend_channel_error_code' => $input['errorCode'],
            'vend_channel_error_id' => $input['vendChannelErrorID'],
            'vend_transaction_id' => $vendTransaction->id,
        ]);
    }

    private function createVendChannel($vendID, $channelCode)
    {
        $vendChannel = VendChannel::create([
            'code' => $channelCode,
            'vend_id' => $vendID,
        ]);

        return $vendChannel;
    }

    private function processMapping($vend, $input)
    {
        $gstVatRate = 0;
        $isPaymentReceived = false;
        $isSuccessful = false;
        $paymentMethod = isset($input['paymentMethodCode']) ? PaymentMethod::where('code', $input['paymentMethodCode'])->first() : null;
        $product = null;
        $unitCost = null;
        $unitCostId = null;
        $vendChannel = VendChannel::where('code', $input['vendChannelCode'])->where('code', '!=', 0)->where('vend_id', $vend->id)->first();
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
        // if(isset($input['orderID'])) {
        //     $paymentGatewayLog = PaymentGatewayLog::where('order_id', $input['orderID'])->where('status', PaymentGatewayLog::STATUS_APPROVE)->first();
        // }

        // mapping product ID, and find unit cost, gst rate
        if(isset($vendChannel) and $vendChannel and $vend->productMapping()->exists()) {
            $productMappingItem = $vend->productMapping->productMappingItems()->where('channel_code', $vendChannel->code)->first();
            if($productMappingItem) {
                $product = $productMappingItem->product;
                $unitCost = $product->unitCosts()->where('is_current', true)->first();
                $gstVatRate = $product->operator ? $product->operator->gst_vat_rate : 0;
            }
        }

        // handle not found vend channel
        if(!$vendChannel and $input['vendChannelCode'] != 0){
            $vendChannel = $this->createVendChannel($vend->id, $input['vendChannelCode']);
        }

        return [
            'amount' => isset($input['amount']) ? $input['amount'] : 0,
            'children' => isset($input['children']) ? $input['children'] : [],
            'dcvendUserID' => isset($input['dcvendUserID']) ? $input['dcvendUserID'] : null,
            'dcvendDiscountAmount' => isset($input['dcvendDiscountAmount']) ? $input['dcvendDiscountAmount'] : null,
            'errorCode' => $input['errorCode'],
            'gstVatRate' => $gstVatRate,
            'interfaceType' => isset($input['interfaceType']) ? $input['interfaceType'] : null,
            'isMultiple' => isset($input['isMultiple']) ? $input['isMultiple'] : false,
            'isPaymentReceived' => $isPaymentReceived,
            'isSuccessful' => $isSuccessful,
            'orderID' => isset($input['orderID']) ? $input['orderID'] : null,
            'originalJson' => isset($input['originalJson']) ? $input['originalJson'] : null,
            // 'paymentGatewayLogID' => isset($paymentGatewayLog) ? $paymentGatewayLog->id : null,
            'paymentMethodCode' => isset($input['paymentMethodCode']) ? $input['paymentMethodCode'] : null,
            'paymentMethodID' => $paymentMethod ? $paymentMethod->id : null,
            'planItemID' => isset($input['planItemID']) ? $input['planItemID'] : null,
            'productID' => $product ? $product->id : null,
            'qty' => isset($input['qty']) ? $input['qty'] : 1,
            'success_qty' => isset($input['success_qty']) ? $input['success_qty'] : 0,
            'time' => isset($input['time']) ? $input['time'] : null,
            'unitCostID' => $unitCost ? $unitCost->id : null,
            'vendChannelCode' => $input['vendChannelCode'],
            'vendChannelError' => $vendChannelError,
            'vendChannelErrorID' => $vendChannelError ? $vendChannelError->id : null,
            'vendChannelID' => $vendChannel ? $vendChannel->id : 0,
            'vouchers' => isset($input['vouchers']) ? $input['vouchers'] : null,
            'hid_card_id' => isset($input['hid_card_id']) ? $input['hid_card_id'] : null,
        ];
    }

    private function processInput($vend, $input)
    {
        $data = [];

        $data['originalJson'] = $input;
        $data['amount'] = isset($input['Price']) ? (isset($input['transf_info']) ? ($input['Price'] * 100) : $input['Price']) : 0;
        $data['dcvendUserID'] = isset($input['dcvend_user_id']) ? $input['dcvend_user_id'] : null;
        $data['dcvendDiscountAmount'] = isset($input['dcvend_discount_amount']) ? $input['dcvend_discount_amount'] : null;
        $data['orderID'] = isset($input['ORDRID']) ? $input['ORDRID'] : null;
        $data['paymentMethodCode'] = isset($input['PAY_TYPE']) ? $input['PAY_TYPE'] : null;
        $data['planItemID'] = isset($input['plan_item_id']) ? $input['plan_item_id'] : null;
        $data['time'] = isset($input['TIME']) ? $input['TIME'] : Carbon::now()->toDateTimeString();
        $data['errorCode'] = isset($input['SErr']) ? $input['SErr'] : (isset($input['errorCode']) ? $input['errorCode'] : 0);
        $data['vendChannelCode'] = isset($input['SId']) ? $input['SId'] : 0;
        $data['interfaceType'] = isset($input['TXN_SRC']) ? $input['TXN_SRC'] : null;
        $data['isMultiple'] = false;
        $data['children'] = [];
        $data['qty'] = 1;
        $data['vouchers'] = isset($input['vouchers']) ? $input['vouchers'] : null;
        $data['hid_card_id'] = isset($input['hid_card_id']) ? $input['hid_card_id'] : null;

        if(isset($input['SErr']) and ($input['SErr'] == 0 or $input['SErr'] == 6)) {
            $data['success_qty'] = 1;
        } else {
            $data['success_qty'] = 0;
        }

        if(isset($input['transf_info']) and sizeof($input['transf_info']) == 1) {
            $data['qty'] = 1;
            $data['isMultiple'] = false;
            $data['errorCode'] = $input['transf_info'][0]['SErr'];
            $data['vendChannelCode'] = $input['transf_info'][0]['SId'];

            if($input['transf_info'][0]['SErr'] == 0 or $input['transf_info'][0]['SErr'] == 6) {
                $data['success_qty'] = 1;
            }
        }

        if(isset($input['transf_info']) and sizeof($input['transf_info']) > 1) {
            $data['isMultiple'] = true;
            $data['qty'] = sizeof($input['transf_info']);
            foreach($input['transf_info'] as $trans) {
                $data['children'][] = $this->processMapping($vend, [
                    'errorCode' => $trans['SErr'],
                    'vendChannelCode' => $trans['SId'],
                ]);
                $data['success_qty'] += ($trans['SErr'] == 0 or $trans['SErr'] == 6) ? 1 : 0;
            }
        }

        return $data;
    }

    public function setDcvendParam($vendTransactionID)
    {
        $vendTransaction = VendTransaction::with([
            'customer:id,name',
            'paymentMethod:id,name',
            'vend:id,code,vend_prefix_id',
            'vend.vendPrefix:id,name',
            'vendTransactionItems.product:id,name',
            'vendTransactionItems.product.thumbnail',
            'vendTransactionItems.vendChannelError:id,code',
        ])
        ->find($vendTransactionID);

        $data = [
            'id' => $vendTransaction->id,
            'apk_ver' => isset($vendTransaction->apk_ver_json['apkver']) ? $vendTransaction->apk_ver_json['apkver'] : null,
            'datetime' => $vendTransaction->created_at,
            'firmware_ver' => isset($vendTransaction->parameter_json['Ver']) ? ($vendTransaction->parameter_json['Ver']).toString(16) : null,
            'total_amount' => $vendTransaction->amount,
            'customer_id' => $vendTransaction->customer_id,
            'customer_name' => $vendTransaction->customer?->name,
            'payment_method_id' => $vendTransaction->payment_method_id,
            'payment_method_name' => $vendTransaction->paymentMethod?->name,
            'plan_item_id' => isset($vendTransaction->vend_transaction_json['plan_item_id']) ? $vendTransaction->vend_transaction_json['plan_item_id'] : null,
            'ref_order_id' => $vendTransaction->order_id,
            'total_promo_amount' => isset($vendTransaction->vend_transaction_json['dcvend_discount_amount']) ? $vendTransaction->vend_transaction_json['dcvend_discount_amount'] : 0,
            'total_qty' => $vendTransaction->vendTransactionItems ? $vendTransaction->vendTransactionItems->count() : 1,
            'user_id' => isset($vendTransaction->vend_transaction_json['dcvend_user_id']) ? $vendTransaction->vend_transaction_json['dcvend_user_id'] : null,
            'vend_code' => $vendTransaction->vend?->code,
            'vend_id' => $vendTransaction->vend_id,
            'vend_prefix_id' => $vendTransaction->vend?->vend_prefix_id,
            'vend_prefix_name' => $vendTransaction->vend?->vendPrefix?->name,
            'vouchers' => isset($vendTransaction->meta_json['vouchers']) ? $vendTransaction->meta_json['vouchers'] : null,
        ];

        if($vendTransaction->vendTransactionItems) {
            $data['items'] = $vendTransaction->vendTransactionItems->map(function($item){
                return [
                    'product_id' => $item->product?->id,
                    'product_name' => $item->product?->name,
                    'product_thumbnail_url' => $item->product?->thumbnail?->full_url,
                    'qty' => $item->qty ?? 1,
                    'vend_channel_code' => $item->vend_channel_code,
                    'vend_channel_id' => $item->vend_channel_id,
                    'vend_channel_error_code' => $item->vend_channel_error_code,
                    'vend_channel_error_name' => $item->vendChannelError?->desc,
                    'vend_channel_error_id' => $item->vend_channel_error_id,
                ];
            });
        }

        if(count($vendTransaction->vendTransactionItems) == 0 and isset($vendTransaction->vend_transaction_json['transf_info']) and count($vendTransaction->vend_transaction_json['transf_info']) > 0) {
            foreach($vendTransaction->vend_transaction_json['transf_info'] as $transfInfo) {

                $product = Product::find($transfInfo['goods_id']);
                $vendChannel = VendChannel::where('code', $transfInfo['SId'])->where('vend_id', $vendTransaction->vend_id)->first();
                $vendChannelError = VendChannelError::where('code', $transfInfo['SErr'])->first();
                $data['items'][] = [
                    'product_id' => $transfInfo['goods_id'],
                    'product_name' => $transfInfo['goods_name'],
                    'product_thumbnail_url' => $product?->thumbnail?->full_url,
                    'qty' => 1,
                    'vend_channel_code' => $transfInfo['SId'],
                    'vend_channel_id' =>  $vendChannel->id,
                    'vend_channel_error_code' => $transfInfo['SErr'],
                    'vend_channel_error_name' => $vendChannelError->desc,
                    'vend_channel_error_id' => $vendChannelError->id,
                ];
            }
        }

        return $data;
    }

    public function syncAllTotalsJson()
    {
        $vends = Vend::with('customer')->has('customer')->where('is_active', true)->get();

        foreach($vends as $vend) {
            SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('default');
        }
    }

}