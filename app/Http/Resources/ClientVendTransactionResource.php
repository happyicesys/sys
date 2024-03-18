<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientVendTransactionResource extends JsonResource
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
            'amount' => number_format($this->amount/ 100, 2, '.', ','),
            'order_id' => $this->order_id,
            'payment_method' => $this->paymentMethod?->name,
            'product_id' => isset($this->product) && $this->product ? $this->product->code : null,
            'product_name' => isset($this->product) && $this->product ? $this->product->name : null,
            'transaction_datetime' => $this->transaction_datetime?->toDatetimeString(),
            'vend_id' => $this->vend->code,
            'vend_name' => $this->vend && $this->vend->customer ? $this->vend->customer->name : $this->vend->name,
            'channel' => $this->vendChannel->code,
            'error' => $this->vendChannelError?->code,
        ];
    }
}
