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
            'amount' => number_format($this->amount/ 100, 2, '.', ','),
            'order_id' => $this->order_id,
            'paymentMethod' => PaymentMethodResource::make($this->paymentMethod),
            'transaction_datetime' => $this->transaction_datetime ? Carbon::parse($this->transaction_datetime)->format('ymd h:i a') : null,
            'vend' => VendResource::make($this->vend),
            'vendChannel' => VendChannelResource::make($this->vendChannel),
            'vendChannelError' => VendChannelErrorResource::make($this->vendChannelError),
            'vendTransactionJson' => $this->vend_transaction_json,
        ];
    }
}
