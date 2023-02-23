<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OperatorPaymentGatewayResource extends JsonResource
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
            'paymentGateway' => PaymentGatewayResource::make($this->whenLoaded('paymentGateway')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'key1' => $this->key1,
            'key2' => $this->key2,
            'key3' => $this->key3,
            'type' => $this->type,
        ];
    }
}
