<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class VendBindingResource extends JsonResource
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
            'begin_date' => Carbon::parse($this->begin_date)->toDateString(),
            'termination_date' => Carbon::parse($this->termination_date)->toDateString(),
            'is_active' => $this->is_active,
            'customer_id' => $this->customer_id,
            'vend_id' => $this->vend_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'vend' => VendResource::make($this->whenLoaded('vend')),
        ];
    }
}
