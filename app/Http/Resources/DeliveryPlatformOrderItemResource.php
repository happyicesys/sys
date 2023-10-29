<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryPlatformOrderItemResource extends JsonResource
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
            'delivery_platform_order_id' => $this->delivery_platform_order_id,
            'deliveryPlatformOrder' => DeliveryPlatformOrderResource::make($this->whenLoaded('deliveryPlatformOrder')),
            'amount' => $this->amount,
            'is_cancelled' => $this->is_cancelled,
            'is_edited' => $this->is_edited,
            'product_id' => $this->product_id,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'product_mapping_item_id' => $this->product_mapping_item_id,
            'productMappingItem' => ProductMappingItemResource::make($this->whenLoaded('productMappingItem')),
            'qty' => $this->qty,
        ];
    }
}
