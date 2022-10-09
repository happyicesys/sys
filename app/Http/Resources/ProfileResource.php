<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
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
            'alias' => $this->alias,
            'name' => $this->name,
            'uen' => $this->uen,
            'base_currency_id' => CountryResource::make($this->whenLoaded('baseCurrency')),
            'address' => AddressResource::make($this->whenLoaded('address')),
            'contact' => ContactResource::make($this->whenLoaded('contact')),
        ];
    }
}
