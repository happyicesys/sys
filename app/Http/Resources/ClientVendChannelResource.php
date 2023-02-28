<?php

namespace App\Http\Resources;

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
            'code' => $this->code,
            'qty' => $this->qty,
            'capacity' => $this->capacity,
            'price' => $this->amount/100,
            'product' => ClientProductResource::make($this->whenLoaded('product')),
        ];
    }
}
