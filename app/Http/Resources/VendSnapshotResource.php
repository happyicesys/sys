<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendSnapshotResource extends JsonResource
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
            'customerJson' => $this->customer_json,
            'operator' => $this->whenLoaded('operator'),
            'parameter_json' => $this->parameter_json,
            'vend' => $this->whenLoaded('vend'),
            'vend_code' => $this->vend_code,
            'vend_channels_json' => $this->vend_channels_json,
            'monthName' => $this->created_at->format('F'),
            'endOfMonthNameYear' => $this->created_at->subDay()->endOfMonth()->format('F Y'),
        ];
    }
}
