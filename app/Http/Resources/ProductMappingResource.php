<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductMappingResource extends JsonResource
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
            'name' => $this->name,
            'currentProductMappings' => ProductMappingResource::collection($this->whenLoaded('currentProductMappings')),
            'upcomingProductMappings' => ProductMappingResource::collection($this->whenLoaded('upcomingProductMappings')),
            'upcomingProductMapping' => ProductMappingResource::make($this->whenLoaded('upcomingProductMapping')),
            'upcoming_product_mapping_id' => $this->upcoming_product_mapping_id,
            'upcoming_product_mapping_name' => $this->relationLoaded('upcomingProductMapping') ? ($this->upcomingProductMapping ? $this->upcomingProductMapping->name : null) : null,
            'is_active' => $this->is_active ? true : false,
            'remarks' => $this->remarks,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'operator_id' => $this->operator_id,
            // 'productMappingItems' => ProductMappingItemResource::collection($this->whenLoaded('productMappingItems')),
            // 'productMappingItemsBySequence' => ProductMappingItemResource::collection($this->whenLoaded('productMappingItemsBySequence')),
            'productMappingItems' => ProductMappingItemResource::collection(
                $this->relationLoaded('productMappingItemsNormalSequence')
                ? $this->productMappingItemsNormalSequence
                : $this->whenLoaded('productMappingItems')
            ),
            // 'productMappingItemsJson' => $this->product_mapping_items_json,
            'selling_price_type' => $this->selling_price_type,
            // Smart-freezer planogram fields. is_smart drives the Edit-page UI
            // branch (basket grid vs the classic channel-row table); the layout
            // JSON shapes the per-basket division count (1a/1b/... vs single "3").
            'is_smart' => (bool) $this->is_smart,
            'basket_layout_json' => $this->basket_layout_json,
            'vends' => VendResource::collection($this->whenLoaded('vends')),
            'vends_count' => isset($this->vends_count) ? $this->vends_count : null,
            'vendPrefixes' => VendPrefixResource::collection($this->whenLoaded('vendPrefixes')),
        ];
    }
}
