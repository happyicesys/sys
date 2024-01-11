<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryPlatformCampaignItemVendResource extends JsonResource
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
            'deliveryPlatformCampaign' => DeliveryPlatformCampaignResource::make($this->whenLoaded('deliveryPlatformCampaign')),
            'deliveryPlatformCampaignItem' => DeliveryPlatformCampaignItemResource::make($this->whenLoaded('deliveryPlatformCampaignItem')),
            'deliveryProductMappingVend' => DeliveryProductMappingVendResource::make($this->whenLoaded('deliveryProductMappingVend')),
            'is_active' => $this->is_active,
            'is_submitted' => $this->is_submitted,
            'platform_ref_id' => $this->platform_ref_id,
        ];
    }
}
