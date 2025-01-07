<?php

namespace App\Http\Resources\DCVend;

use App\Http\Resources\AddressResource;
use App\Http\Resources\AttachmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'address' => AddressResource::make($this->whenLoaded('deliveryAddress')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'name' => $this->name,
            'is_active' => $this->is_active,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
