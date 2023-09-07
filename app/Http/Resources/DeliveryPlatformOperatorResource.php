<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryPlatformOperatorResource extends JsonResource
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
            'deliveryPlatform' => DeliveryPlatformResource::make($this->whenLoaded('deliveryPlatform')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'field1' => $this->field1,
            'field2' => $this->field2,
            'field3' => $this->field3,
            'field4' => $this->field4,
            'type' => $this->type,
        ];
    }
}
