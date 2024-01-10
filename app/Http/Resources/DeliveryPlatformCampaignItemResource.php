<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryPlatformCampaignItemResource extends JsonResource
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
            'datetime_from' => $this->datetime_from ? $this->datetime_from->setTimezone($request->user()->timezone)->format('Y-m-d H:i:s') : null,
            'datetime_to' => $this->datetime_to ? $this->datetime_to->setTimezone($request->user()->timezone)->format('Y-m-d H:i:s') : null,
            'is_active' => $this->is_active,
            'items_json' => $this->items_json,
            'settings_json' => $this->settings_json,
            'settings_label' => $this->settings_label,
            'settings_name' => $this->settings_name,
        ];
    }
}
