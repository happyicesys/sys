<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryPlatformOrderComplaintResource extends JsonResource
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
            'delivery_platform_order_id' => $this->delivery_platform_order_id,
            'driver_phone_number' => $this->driver_phone_number,
            'original_json' => $this->original_json,
            'remarks' => $this->remarks,
        ];
    }
}
