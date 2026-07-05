<?php

namespace App\Http\Resources;

use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class VendTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount / 100,
            'avg_seven_days_amount' => isset($this->avg_seven_days_amount) ? $this->avg_seven_days_amount : null,
            'cashless_mfg' => $this->cashless_mfg,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'customer_code' => isset($this->customer_code) ? $this->customer_code : null,
            'customer_name' => isset($this->customer_name) ? $this->customer_name : null,
            'interface_type' => isset($this->interface_type) ? $this->interface_type : null,
            'is_multiple' => $this->is_multiple,
            'is_payment_received' => $this->is_payment_received,
            'is_refunded' => isset($this->is_refunded) && $this->is_refunded ? true : false,
            // Refund badge: 'auto' | 'manual' | null, plus the ticket reference
            // (RF-xxxxxx) when there is one. Populated per-page in transactionIndex.
            'refund_type' => $this->refund_type ?? null,
            'refund_reference' => $this->refund_reference ?? null,
            'is_found_in_transaction' => (bool) ($this->is_found_in_transaction ?? true),
            'settlement_status' => $this->settlement_status ?? null,
            'itemsJson' => $this->items_json,
            'labelJson' => (function () {
                $computed = is_string($this->label_json) ? json_decode($this->label_json, true) : ($this->label_json ?? []);
                $raw = is_string($this->raw_label_json) ? json_decode($this->raw_label_json, true) : ($this->raw_label_json ?? []);

                $merged = collect($computed ?? []);
                if ($raw && is_array($raw)) {
                    foreach ($raw as $r) {
                        if (is_string($r)) {
                            $merged->push($r);
                        }
                    }
                }
                return $merged;
            })(),
            'metaJson' => $this->meta_json,
            'order_id' => $this->order_id,
            'operator_code' => isset($this->operator_code) ? $this->operator_code : null,
            'paymentMethod' => PaymentMethodResource::make($this->whenLoaded('paymentMethod')),
            'payment_method_name' => isset($this->payment_method_name) ? $this->payment_method_name : null,
            'person_id' => isset($this->person_id) ? $this->person_id : null,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'product_id' => isset($this->product_id) ? $this->product_id : null,
            'product_code' => isset($this->product_code) ? $this->product_code : null,
            'product_name' => isset($this->product_name) ? $this->product_name : null,
            'transaction_datetime' => $this->transaction_datetime ? Carbon::parse($this->transaction_datetime)->setTimezone($this->getUserTimezone())->format('ymd h:ia') : null,
            // 'grossProfit' => $this->when($this->relationLoaded('product'), function(){
            //     number_format($this->getGrossProfit()/ 100, 2, '.', ',');
            // }),
            // 'unitCost' => $this->when($this->relationLoaded('unitCost'), function(){
            //     number_format($this->getUnitCost()/ 100, 2, '.', ',');
            // }),
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'vendChannel' => VendChannelResource::make($this->whenLoaded('vendChannel')),
            'vend_channel_code' => $this->vend_channel_code,
            'vend_channel_amount' => isset($this->vend_channel_amount) ? $this->vend_channel_amount / 100 : null,
            'vend_channel_amount2' => isset($this->vend_channel_amount2) ? $this->vend_channel_amount2 / 100 : null,
            'vend_channel_error_code' => isset($this->vend_channel_error_code) ? $this->vend_channel_error_code : null,
            'vend_channel_error_desc' => isset($this->vend_channel_error_desc) ? $this->vend_channel_error_desc : null,
            'vend_code' => isset($this->vend_code) ? $this->vend_code : null,
            'vend_name' => isset($this->vend_name) ? $this->vend_name : null,
            'vend_prefix_name' => isset($this->vend_prefix_name) ? $this->vend_prefix_name : null,
            'vendChannelError' => VendChannelErrorResource::make($this->whenLoaded('vendChannelError')),
            'vendTransactionJson' => $this->vend_transaction_json,
            'vendTransactionItems' => VendTransactionItemResource::collection($this->whenLoaded('vendTransactionItems')),
            'virtual_customer_prefix' => isset($this->virtual_customer_prefix) ? $this->virtual_customer_prefix : null,
            'virtual_customer_code' => isset($this->virtual_customer_code) ? $this->virtual_customer_code : null,
        ];
    }
}
