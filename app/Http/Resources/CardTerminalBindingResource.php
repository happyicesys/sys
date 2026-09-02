<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CardTerminalBindingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'terminal_id' => $this->terminal_id,
            'vend_id' => $this->vend_id,
            'vend_code' => $this->vend?->code,
            'vend_name' => $this->vend?->name,
            'customer_name' => $this->vend?->customer?->name,
            'bound_from' => $this->bound_from?->format('Y-m-d'),
            'bound_until' => $this->bound_until?->format('Y-m-d'),
            'remarks' => $this->remarks,
        ];
    }
}
