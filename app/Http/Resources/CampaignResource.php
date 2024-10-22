<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'campaignItems' => CampaignItemResource::collection($this->whenLoaded('campaignItems')),
            'is_active' => $this->is_active ? true : false,
            'name' => $this->name,
            'operator_id' => $this->operator_id,
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'remarks' => $this->remarks,
        ];
    }
}
