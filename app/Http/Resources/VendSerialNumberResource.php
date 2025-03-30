<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendSerialNumberResource extends JsonResource
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
            'code' => $this->code,
            'desc' => $this->desc,
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'vend_code' => isset($this->vend_code) ? $this->vend_code : null,
            'vend_begin_date' => isset($this->vend_begin_date) ? Carbon::parse($this->vend_begin_date)->toDateString() : null,
            'vend_model_name' => isset($this->vend_model_name) ? $this->vend_model_name : null,
            'vend_config_name' => isset($this->vend_config_name) ? $this->vend_config_name : null,
            'vend_prefix_name' => isset($this->vend_prefix_name) ? $this->vend_prefix_name : null,
            'vend_status' => isset($this->vend_status) ? $this->vend_status : null,
        ];
    }
}
