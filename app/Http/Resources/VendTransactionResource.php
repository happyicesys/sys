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
            'amount' => $this->amount/ 100,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'customer_json' => $this->customer_json,
            // 'customerCode' => $this->customer_json && isset($this->customer_json['code']) ? $this->customer_json['code'] : ($this->vend_json && isset($this->vend_json['latest_vend_binding']) ? $this->vend_json['latest_vend_binding']['customer']['code'] : null),
            // 'customerName' => $this->customer_json && isset($this->customer_json['name']) ? $this->customer_json['name'] : ($this->vend_json && isset($this->vend_json['latest_vend_binding']) ? $this->vend_json['latest_vend_binding']['customer']['name'] : ($this->name ? $this->name : null)),
            'is_multiple' => $this->is_multiple,
            'is_payment_received' => $this->is_payment_received,
            'itemsJson' => $this->items_json,
            'locationTypeJson' => $this->location_type_json,
            'order_id' => $this->order_id,
            'operatorJson' => $this->operator_json,
            'paymentMethod' => PaymentMethodResource::make($this->paymentMethod),
            'product' => ProductResource::make($this->product),
            'productJson' => $this->product_json,
            'transaction_datetime' => $this->transaction_datetime ? Carbon::parse($this->transaction_datetime)->setTimezone($this->getUserTimezone())->format('ymd h:ia') : null,
            // 'grossProfit' => $this->when($this->relationLoaded('product'), function(){
            //     number_format($this->getGrossProfit()/ 100, 2, '.', ',');
            // }),
            // 'unitCost' => $this->when($this->relationLoaded('unitCost'), function(){
            //     number_format($this->getUnitCost()/ 100, 2, '.', ',');
            // }),
            'vend' => VendResource::make($this->vend),
            // 'vendChannel' => VendChannelResource::make($this->vendChannel),
            'vend_channel_code' => $this->vend_channel_code,
            'vendChannelError' => VendChannelErrorResource::make($this->vendChannelError),
            'vendJson' => $this->vend_json,
            'vendTransactionJson' => $this->vend_transaction_json,
            'vendTransactionItemsJson' => $this->vend_transaction_items_json,
        ];
    }
}
