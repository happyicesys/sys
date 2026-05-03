<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReportResource extends JsonResource
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
            'code' => isset($this->code) ? $this->code : null,
            'name' => isset($this->name) ? $this->name : null,
            'vend_model_name' => isset($this->vend_model_name) ? $this->vend_model_name : null,
            'location_type_name' => isset($this->location_type_name) ? $this->location_type_name : null,
            'vend_prefix_name' => isset($this->vend_prefix_name) ? $this->vend_prefix_name : null,
            'product_mapping_name' => isset($this->product_mapping_name) ? $this->product_mapping_name : null,
            'count' => isset($this->count) ? $this->count : 0,
            'error_count' => isset($this->error_count) ? (int)$this->error_count : 0,
            'error_count_no_4_5' => isset($this->error_count_no_4_5) ? (int)$this->error_count_no_4_5 : 0,
            'error_count_4_5' => isset($this->error_count_4_5) ? (int)$this->error_count_4_5 : 0,
            'channel_availability' => isset($this->channel_availability) ? (int)$this->channel_availability : null,
            'amount' => isset($this->amount) ? $this->amount/100 : 0,
        ];
    }
}
