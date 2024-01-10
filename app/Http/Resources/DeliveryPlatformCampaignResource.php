<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryPlatformCampaignResource extends JsonResource
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
            'deliveryProductMapping' => DeliveryProductMappingResource::make($this->whenLoaded('deliveryProductMapping')),
            'deliveryPlatformCampaignItems' => DeliveryPlatformCampaignItemResource::collection($this->whenLoaded('deliveryPlatformCampaignItems')),
            'deliveryPlatformOperator' => DeliveryPlatformOperatorResource::make($this->whenLoaded('deliveryPlatformOperator')),
            'datetime_from' => $this->datetime_from ? Carbon::parse($this->datetime_from)->setTimezone($request->user()->timezone)->format('Y-m-d H:i:s') : null,
            'datetime_to' => $this->datetime_to ? Carbon::parse($this->datetime_to)->setTimezone($request->user()->timezone)->format('Y-m-d H:i:s') : null,
            'desc' => $this->desc,
            'is_active' => $this->is_active,
            'min_amount' => $this->min_amount,
            'name' => $this->name,
        ];
    }
}
