<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendPrefixResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $upcomingProductMappingsUnique = collect();

        if ($this->relationLoaded('productMappings')) {
            $upcomingProductMappingsUnique = $this->productMappings
                ->flatMap(function ($productMapping) {
                    return $productMapping->upcomingProductMappings;
                })
                ->unique('id')
                ->values();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'desc' => $this->desc,
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'operator_id' => $this->operator_id,
            'productMappings' => ProductMappingResource::collection($this->whenLoaded('productMappings')),
            'product_mapping_id' => $this->product_mapping_id,
            'upcomingProductMappingsUnique' => ProductMappingResource::collection($upcomingProductMappingsUnique),
            'vendConfigs' => VendConfigResource::collection($this->whenLoaded('vendConfigs')),
            // 'vend_config_id' => $this->vend_config_id,
        ];
    }
}
