<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductUomResource extends JsonResource
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
            'product_id' => $this->product_id,
            'uom_id' => $this->uom_id,
            'is_base_uom' => $this->is_base_uom,
            'is_transaction_uom' => $this->is_transaction_uom,
            'value' => $this->value,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'uom' => UomResource::make($this->whenLoaded('uom')),
        ];
    }
}
