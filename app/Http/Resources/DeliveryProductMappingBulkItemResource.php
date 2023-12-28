<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryProductMappingBulkItemResource extends JsonResource
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
            'delivery_product_mapping_bulk_id' => $this->delivery_product_mapping_bulk_id,
            'delivery_product_mapping_item_id' => $this->delivery_product_mapping_item_id,
            'sub_category_json' => $this->sub_category_json,
            'deliveryProductMappingItem' => new DeliveryProductMappingItemResource($this->whenLoaded('deliveryProductMappingItem')),
        ];
    }
}
