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
            'id' => $this->id,
            'uuid' => $this->uuid,
            'campaignItems' => CampaignItemResource::collection($this->whenLoaded('campaignItems')),
            'is_active' => $this->is_active ? true : false,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'start_at' => $this->start_at ? Carbon::parse($this->start_at)->setTimezone($this->getUserTimezone())->toDateString() : null,
            'end_at' => $this->end_at ? Carbon::parse($this->end_at)->setTimezone($this->getUserTimezone())->toDateString() : null,
            'operator_id' => $this->operator_id,
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'remarks' => $this->remarks,
            'promo_type' => $this->promo_type,
            'bundle_qty' => $this->bundle_qty,
            'value' => $this->value,
            'labels_x' => TagResource::collection($this->whenLoaded('labelsX')),
            'labels_y' => TagResource::collection($this->whenLoaded('labelsY')),
        ];
    }
}
