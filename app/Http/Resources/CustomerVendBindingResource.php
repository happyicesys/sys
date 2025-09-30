<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerVendBindingResource extends JsonResource
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
            //format like 2024-09-12 12:16:42
            'created_at' => $this->created_at ?
                $this->created_at->format('Y-m-d H:i:s') :
                null,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'customer_id' => $this->customer_id,
            'is_binding' => $this->is_binding,
            'user' => UserResource::make($this->whenLoaded('user')),
            'user_id' => $this->user_id,
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'vend_id' => $this->vend_id,
            'vend_prefix_id' => $this->vend_prefix_id,
        ];
    }
}
