<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentGatewayResource extends JsonResource
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
            'classname' => $this->classname,
            'country' => CountryResource::make($this->whenLoaded('country')),
            'full_name' => $this->when($this->relationLoaded('country'), function() {
                return $this->country && $this->country->name ? $this->name.' ('.$this->country->name.')' : '';
            }, function(){
                return $this->name;
            }),
            'remarks' => $this->remarks,
            'key1_name' => $this->key1_name,
            'key2_name' => $this->key2_name,
            'key3_name' => $this->key3_name,
        ];
    }
}
