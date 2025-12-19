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
        $labelsX = $this->relationLoaded('labelsX') ? $this->labelsX : collect();
        $labelsY = $this->relationLoaded('labelsY') ? $this->labelsY : collect();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'campaignItems' => CampaignItemResource::collection($this->whenLoaded('campaignItems')),
            'is_active' => $this->is_active ? true : false,
            'is_in_use' => $this->apkSettings()->exists(),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'start_at' => $this->start_at ? Carbon::parse($this->start_at)->setTimezone($this->getUserTimezone())->toDateString() : null,
            'end_at' => $this->end_at ? Carbon::parse($this->end_at)->setTimezone($this->getUserTimezone())->toDateString() : null,
            'operator_id' => $this->operator_id,
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'remarks' => $this->remarks,
            'promo_type' => $this->promo_type,
            'is_using_qty' => $this->is_using_qty,
            'bundle_qty' => $this->bundle_qty,
            'value' => $this->value,
            'min_basket_value' => $this->min_basket_value,
            'max_discount_value' => $this->max_discount_value,
            'labels_x' => TagResource::collection($labelsX),
            'labels_y' => TagResource::collection($labelsY),
            'pivot' => $this->whenPivotLoaded('apk_setting_campaign', function () {
                return [
                    'apk_setting_id' => $this->pivot->apk_setting_id,
                    'campaign_id' => $this->pivot->campaign_id,
                    'created_at' => optional($this->pivot->created_at)
                        ? Carbon::parse($this->pivot->created_at)->setTimezone($this->getUserTimezone())->toDateTimeString()
                        : null,
                    'updated_at' => optional($this->pivot->updated_at)
                        ? Carbon::parse($this->pivot->updated_at)->setTimezone($this->getUserTimezone())->toDateTimeString()
                        : null,
                ];
            }),
        ];
    }
}
