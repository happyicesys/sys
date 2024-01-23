<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryProductMappingVendChannelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
        // return [
        //     'id' => $this->id,
        //     'amount' => $this->amount,
        //     'delivery_product_mapping_id' => $this->delivery_product_mapping_id,
        //     'deliveryProductMapping' => new DeliveryProductMappingResource($this->whenLoaded('deliveryProductMapping')),
        //     'delivery_product_mapping_item_id' => $this->delivery_product_mapping_item_id,
        //     'deliveryProductMappingItem' => new DeliveryProductMappingItemResource($this->whenLoaded('deliveryProductMappingItem')),
        //     'delivery_product_mapping_vend_id' => $this->delivery_product_mapping_vend_id,
        //     'deliveryProductMappingVend' => new DeliveryProductMappingVendResource($this->whenLoaded('deliveryProductMappingVend')),
        //     'is_active' => $this->is_active,
        //     'qty' => $this->qty,
        //     'reserved_percent' => $this->reserved_percent,
        //     'reserved_qty' => $this->reserved_qty,
        //     'vend_channel_code' => $this->vend_channel_code,
        //     'vend_channel_id' => $this->vend_channel_id,
        //     'vendChannel' => new VendChannelResource($this->whenLoaded('vendChannel')),
        //     'vend_code' => $this->vend_code,
        //     'vend_id' => $this->vend_id,
        //     'vend' => new VendResource($this->whenLoaded('vend')),
        // ];
    }
}
