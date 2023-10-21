<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryProductMappingVendResource extends JsonResource
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
            'delivery_product_mapping_id' => $this->delivery_product_mapping_id,
            'is_active' => $this->is_active,
            'delivery_product_mapping_vend_channels_json' => $this->delivery_product_mapping_vend_channels_json,
            'vend_id' => $this->vend_id,
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'deliveryProductMapping' => DeliveryProductMappingResource::make($this->whenLoaded('deliveryProductMapping')),
            'deliveryProductMappingVendChannels' => DeliveryProductMappingVendChannelResource::collection($this->whenLoaded('deliveryProductMappingVendChannels')),
        ];
    }
}
