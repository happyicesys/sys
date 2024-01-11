<?php

namespace App\Http\Resources;

use Carbon\Carbon;
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
            'end_date' => $this->end_date ? Carbon::parse($this->end_date)->setTimezone($this->getUserTimezone())->format('ymd h:ia') : null,
            'vend_id' => $this->vend_id,
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'deliveryPlatformCampaignItemVends' => DeliveryPlatformCampaignItemVendResource::collection($this->whenLoaded('deliveryPlatformCampaignItemVends')),
            'deliveryProductMapping' => DeliveryProductMappingResource::make($this->whenLoaded('deliveryProductMapping')),
            'deliveryProductMappingVendChannels' => DeliveryProductMappingVendChannelResource::collection($this->whenLoaded('deliveryProductMappingVendChannels')),
            'platform_ref_id' => $this->platform_ref_id,
            'start_date' => $this->start_date ? Carbon::parse($this->start_date)->setTimezone($this->getUserTimezone())->format('ymd h:ia') : null,
        ];
    }
}
