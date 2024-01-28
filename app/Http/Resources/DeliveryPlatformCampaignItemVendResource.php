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
            'datetime_from' => $this->datetime_from ? $this->datetime_from->format('Y-m-d H:i:s') : null,
            'datetime_to' => $this->datetime_to ? $this->datetime_to->format('Y-m-d H:i:s') : null,
            'deliveryPlatformCampaign' => DeliveryPlatformCampaignResource::make($this->whenLoaded('deliveryPlatformCampaign')),
            'deliveryPlatformCampaignItem' => DeliveryPlatformCampaignItemResource::make($this->whenLoaded('deliveryPlatformCampaignItem')),
            'deliveryProductMappingVend' => DeliveryProductMappingVendResource::make($this->whenLoaded('deliveryProductMappingVend')),
            'is_active' => $this->is_active,
            'is_submitted' => $this->is_submitted,
            'platform_ref_id' => $this->platform_ref_id,
            'settings_json' => $this->settings_json,
            'settings_label' => $this->settings_label,
            'settings_name' => $this->settings_name,
        ];
    }
}
