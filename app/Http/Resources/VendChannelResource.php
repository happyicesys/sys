<?php

namespace App\Http\Resources;

use App\Http\Resources\VendChannelErrorLogResource;
use Illuminate\Http\Resources\Json\JsonResource;

class VendChannelResource extends JsonResource
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
            'code' => $this->code,
            'qty' => $this->qty,
            'capacity' => $this->capacity,
            'amount' => $this->amount,
            'is_active' => $this->is_active ? true : false,
            'vendChannelErrorLogs' => VendChannelErrorLogResource::collection($this->whenLoaded('vendChannelErrorLogs')),
            'product' => ProductResource::make($this->whenLoaded('product')),
        ];
    }
}
