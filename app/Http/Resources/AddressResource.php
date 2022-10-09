<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
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
            'name' => $this->name,
            'block_num' => $this->block_num,
            'country_id' => CountryResource::make($this->whenLoaded('country')),
            'building' => $this->building,
            'full_address' => ($this->unit_num ? '#'.$this->unit_num.', ' : '')
                                .($this->block_num ? 'Blk '.ucwords(strtolower($this->block_num)).', ' : '')
                                .($this->building ? ucwords(strtolower($this->building)).', ' : '')
                                .($this->street_name ? ucwords(strtolower($this->street_name)).', ' : '')
                                .$this->postcode.' '.$this->country->name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'postcode' => $this->postcode,
            'sequence' => $this->sequence,
            'street_name' => $this->street_name,
            'type' => $this->type,
            'unit_num' => $this->unit_num,
        ];
    }
}
