<?php

namespace App\Services;
use App\Models\Product;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use Carbon\Carbon;

class VendTransactionService
{
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
            'ref_order_id' => $vendTransaction->order_id,
            'total_promo_amount' => isset($vendTransaction->vend_transaction_json['dcvend_discount_amount']) ? $vendTransaction->vend_transaction_json['dcvend_discount_amount'] : 0,
            'total_qty' => $vendTransaction->vendTransactionItems ? $vendTransaction->vendTransactionItems->count() : 1,
            'user_id' => isset($vendTransaction->vend_transaction_json['dcvend_user_id']) ? $vendTransaction->vend_transaction_json['dcvend_user_id'] : null,
            'vend_code' => $vendTransaction->vend?->code,
            'vend_id' => $vendTransaction->vend_id,
            'vend_prefix_id' => $vendTransaction->vend?->vend_prefix_id,
            'vend_prefix_name' => $vendTransaction->vend?->vendPrefix?->name,
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

}