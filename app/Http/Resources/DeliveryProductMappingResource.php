<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryProductMappingResource extends JsonResource
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
            'category_json' => $this->category_json,
            'delivery_platform_operator_id' => $this->delivery_platform_operator_id,
            'is_active' => $this->is_active,
            'name' => $this->name,
            'operator_id' => $this->operator_id,
            'product_mapping_id' => $this->product_mapping_id,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'reserved_percent' => $this->reserved_percent,
            'reserved_qty' => $this->reserved_qty,
            'deliveryPlatformOperator' => DeliveryPlatformOperatorResource::make($this->whenLoaded('deliveryPlatformOperator')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'productMapping' => ProductMappingResource::make($this->whenLoaded('productMapping')),
            'deliveryProductMappingItems' => DeliveryProductMappingItemResource::collection($this->whenLoaded('deliveryProductMappingItems')),
            'deliveryProductMappingItemsJson' => $this->delivery_product_mapping_items_json,
            'deliveryProductMappingVends' => DeliveryProductMappingVendResource::collection($this->whenLoaded('deliveryProductMappingVends')),
        ];
    }
}
