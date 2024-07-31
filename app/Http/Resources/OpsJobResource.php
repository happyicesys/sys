<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsJobResource extends JsonResource
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
            'date' => $this->date->format('ymd'),
            'status' => $this->status,
            'createdBy' => UserResource::make($this->whenLoaded('createdBy')),
            'deliveredBy' => UserResource::make($this->whenLoaded('deliveredBy')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'opsJobItems' => OpsJobItemResource::collection($this->whenLoaded('opsJobItems')),
            'ops_job_items_count' => isset($this->ops_job_items_count) ? $this->ops_job_items_count : 0,
            'picked_at' => $this->picked_at,
            'pickedBy' => UserResource::make($this->whenLoaded('pickedBy')),
            'updatedBy' => UserResource::make($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at->format('ymd h:i a'),
            'updated_at' => $this->updated_at->format('ymd h:i a'),
        ];
    }
}
