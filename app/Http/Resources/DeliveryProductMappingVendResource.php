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
            'binded_times' => isset($this->binded_times) ? $this->binded_times : null,
            'delivery_platform_orders_sum_subtotal_amount' => $this->delivery_platform_orders_sum_subtotal_amount ? intval($this->delivery_platform_orders_sum_subtotal_amount)/ 100 : 0,
            'delivery_platform_orders_sum_promo_amount' => $this->delivery_platform_orders_sum_promo_amount ? intval($this->delivery_platform_orders_sum_promo_amount)/ 100 : 0,
            'delivery_platform_orders_count' => $this->delivery_platform_orders_count,
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
            'start_date_iso' => $this->start_date ? Carbon::parse($this->start_date)->setTimezone($this->getUserTimezone())->toIso8601String() : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->setTimezone($this->getUserTimezone())->format('ymd h:ia') : null,
            'created_at_iso' => $this->created_at ? Carbon::parse($this->created_at)->setTimezone($this->getUserTimezone())->toIso8601String() : null,
            'end_date_iso' => $this->end_date ? Carbon::parse($this->end_date)->setTimezone($this->getUserTimezone())->toIso8601String() : null,
        ];
    }
}
