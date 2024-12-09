<?php

namespace App\Http\Resources;

use App\Models\CampaignItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignItemResource extends JsonResource
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
            'apk_setting_id' => $this->apk_setting_id,
            'apkSetting' => ApkSettingResource::make($this->whenLoaded('apkSetting')),
            'name' => $this->name,
            'promo_type' => $this->promo_type ? CampaignItem::PROMO_TYPE_MAPPINGS[$this->promo_type] : null,
            'qty' => $this->qty,
            'remarks' => $this->remarks,
            'tagBindings' => TagBindingResource::collection($this->whenLoaded('tagBindings')),
            'value' => $this->value,
        ];
    }
}
