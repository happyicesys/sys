<?php

namespace App\Http\Resources\V1\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientVendChannelResource extends JsonResource
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
            'channel_code' => $this->code,
            'qty' => $this->qty,
            'capacity' => $this->capacity,
            'price' => $this->amount/100,
            'product_id' => $this->whenLoaded('product', fn() => $this->product?->code),
            'product_name' => $this->whenLoaded('product', fn() => $this->product?->name),
        ];
    }
}
