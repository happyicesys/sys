<?php

namespace App\Http\Resources\DCVend;

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
            'photos' => AttachmentResource::collection($this->whenLoaded('photos')),
            'name' => $this->name,
        ];
    }
}
