<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryProductMappingItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'channel_code' => $this->channel_code,
            'delivery_product_mapping_id' => $this->delivery_product_mapping_id,
            'product_mapping_id' => $this->product_mapping_id,
            'product_mapping_item_id' => $this->product_mapping_item_id,
            'sub_category_json' => $this->sub_category_json,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'productMapping' => ProductMappingResource::make($this->whenLoaded('productMapping')),
            'productMappingItem' => ProductMappingItemResource::make($this->whenLoaded('productMappingItem')),
        ];
    }
}
