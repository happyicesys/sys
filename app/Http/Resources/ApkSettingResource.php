<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApkSettingResource extends JsonResource
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
            'campaignImages' => AttachmentResource::collection($this->whenLoaded('campaignImages')),
            'campaignItems' => CampaignItemResource::collection($this->whenLoaded('campaignItems')),
            'campaigns' => CampaignResource::collection($this->whenLoaded('campaigns')),
            'campaignVideos' => AttachmentResource::collection($this->whenLoaded('campaignVideos')),
            'name' => $this->name,
            'images' => AttachmentResource::collection($this->whenLoaded('images')),
            'remarks' => $this->remarks,
            'settings_parameter_json' => $this->settings_parameter_json,
            'vends' => VendResource::collection($this->whenLoaded('vends')),
            'is_in_use' => $this->vends()->exists(),
            'videos' => AttachmentResource::collection($this->whenLoaded('videos')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
