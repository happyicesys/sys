<?php

namespace App\Http\Resources\DCVend;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendChannelResource extends JsonResource
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
            'qty' => $this->qty,
            'locked_qty' => $this->locked_qty,
            'capacity' => $this->capacity,
            'amount' => $this->amount ? $this->amount/ 100 : 0,
            'amount2' => $this->amount2 ? $this->amount2/ 100 : 0,
            'is_active' => $this->is_active ? true : false,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'server_amount' => isset($this->server_amount) ? $this->server_amount/ 100 : 0,
        ];
    }
}
