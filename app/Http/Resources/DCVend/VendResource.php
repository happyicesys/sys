<?php

namespace App\Http\Resources\DCVend;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'is_active' => $this->is_active,
            'vendChannels' => VendChannelResource::collection($this->whenLoaded('vendChannels')),
        ];
    }
}
