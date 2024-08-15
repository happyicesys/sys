<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendChannelRecordResource extends JsonResource
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
            'after_data_json' => $this->after_data_json,
            'after_data_created_at' => $this->after_data_created_at,
            'after_date_created_at_formatted' => isset($this->after_data_created_at) ? $this->after_data_created_at->format('ymd h:i a') : '',
            'after_label' => $this->after_label,
            'before_data_json' => $this->before_data_json,
            'before_data_created_at' => $this->before_data_created_at,
            'before_date_created_at_formatted' => isset($this->before_data_created_at) ? $this->before_data_created_at->format('ymd h:i a') : '',
            'before_label' => $this->before_label,
            'stage_data_json' => $this->stage_data_json,
            'stage_data_created_at' => $this->stage_data_created_at,
            'stage_date_created_at_formatted' => isset($this->stage_data_created_at) ? $this->stage_data_created_at->format('ymd h:i a') : '',
            'stage_label' => $this->stage_label,
            'customer_id' => $this->customer_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'operator_id' => $this->operator_id,
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'vend_id' => $this->vend_id,
            'vend' => VendResource::make($this->whenLoaded('vend')),
        ];
    }
}
