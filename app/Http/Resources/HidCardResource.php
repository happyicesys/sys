<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HidCardResource extends JsonResource
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
            'value' => $this->value,
            'operator_id' => $this->operator_id,
            'operator' => new OperatorResource($this->whenLoaded('operator')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'vends' => VendResource::collection($this->whenLoaded('vends')),
        ];
    }
}
