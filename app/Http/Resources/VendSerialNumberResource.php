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
            'customer_name' => isset($this->customer_name) ? $this->customer_name : null,
            'customer_virtual_code' => isset($this->customer_virtual_code) ? $this->customer_virtual_code : null,
            'desc' => $this->desc,
            'location_type_name' => isset($this->location_type_name) ? $this->location_type_name : null,
            'operator_name' => isset($this->operator_name) ? $this->operator_name : null,
            'postcode' => isset($this->postcode) ? $this->postcode : null,
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'vend_id' => isset($this->vend_id) ? $this->vend_id : null,
            'vend_code' => isset($this->vend_code) ? $this->vend_code : null,
            'vend_contract_name' => isset($this->vend_contract_name) ? $this->vend_contract_name : null,
            'vend_begin_date' => isset($this->vend_begin_date) ? Carbon::parse($this->vend_begin_date)->toDateString() : null,
            'vend_lcd_monitor' => isset($this->vend_lcd_monitor) ? $this->vend_lcd_monitor : null,
            'vend_model_name' => isset($this->vend_model_name) ? $this->vend_model_name : null,
            'vend_config_name' => isset($this->vend_config_name) ? $this->vend_config_name : null,
            'vend_prefix_name' => isset($this->vend_prefix_name) ? $this->vend_prefix_name : null,
            'vend_status' => isset($this->vend_status) ? $this->vend_status : null,
        ];
    }
}
