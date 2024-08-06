<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsJobItemChannelResource extends JsonResource
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
            'actual_qty' => $this->actual_qty,
            'capacity' => $this->capacity,
            'ops_job_id' => $this->ops_job_id,
            'ops_job_item_id' => $this->ops_job_item_id,
            'picked_qty' => $this->picked_qty,
            'product_id' => $this->product_id,
            'vendChannel' => VendChannelResource::make($this->whenLoaded('vendChannel')),
            'vend_channel_id' => $this->vend_channel_id,
            'vend_channel_code' => $this->vend_channel_code,
            'vend_code' => $this->vend_code,
        ];
    }
}
