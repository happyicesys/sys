<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryProductMappingBulkResource extends JsonResource
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
            'delivery_platform_campaign_id' => $this->delivery_platform_campaign_id,
            'delivery_product_mapping_id' => $this->delivery_product_mapping_id,
            'group_json' => $this->group_json,
            'is_active' => $this->is_active,
            'name' => $this->name,
            'promo_desc' => $this->promo_desc,
            'promo_label' => $this->promo_label,
            'promo_type' => $this->promo_type,
            'promo_value' => $this->promo_value,
            'total_qty' => $this->total_qty,
            'delivery_product_mapping_bulk_items' => DeliveryProductMappingBulkItemResource::collection($this->whenLoaded('deliveryProductMappingBulkItems')),
        ];
    }
}
