<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemVendChannelResource extends JsonResource
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
            'delivery_platform_order_item_id' => $this->delivery_platform_order_item_id,
            'deliveryPlatformOrderItem' => DeliveryPlatformOrderItemResource::make($this->whenLoaded('deliveryPlatformOrderItem')),
            'delivery_product_mapping_vend_channel_id' => $this->delivery_product_mapping_vend_channel_id,
            'deliveryProductMappingVendChannel' => DeliveryProductMappingVendChannelResource::make($this->whenLoaded('deliveryProductMappingVendChannel')),
            'vend_channel_id' => $this->vend_channel_id,
            'vendChannel' => VendChannelResource::make($this->whenLoaded('vendChannel')),
        ];
    }
}
