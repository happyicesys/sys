<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductMappingItemResource extends JsonResource
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
            'channel_code' => $this->channel_code,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'productMapping' => ProductMappingResource::make($this->whenLoaded('productMapping')),
        ];
    }
}
