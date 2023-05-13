<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendSnapshotDBResource extends JsonResource
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
            'created_at' => isset($this->created_at) ? $this->created_at->format('ymd h:ia') : null,
            'customer_code' => isset($this->customer_code) ? $this->customer_code : null,
            'customer_name' => isset($this->customer_name) ? $this->customer_name : null,
            'full_name' => isset($this->customer_code) ? $this->customer_code . ' - ' . $this->customer_name : $this->vend_name,
            'month_number' => isset($this->month_number) ? $this->month_number : null,
            'parameter_json' => isset($this->parameter_json) ? json_decode($this->parameter_json) : null,
            'vend_channels_json' => isset($this->vend_channels_json) ? json_decode($this->vend_channels_json) : null,
            'vend_code' => isset($this->vend_code) ? $this->vend_code : null,
            'vend_name' => isset($this->vend_name) ? $this->vend_name : null,
            'year_number' => isset($this->year_number) ? $this->year_number : null,

        ];
    }
}
