<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'is_active' => $this->is_active,
            'username' => $this->username,
            'operator_id' => $this->operator_id,
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'phoneCountry' => CountryResource::make($this->whenLoaded('phoneCountry')),
            'phone_country_id' => CountryResource::make($this->whenLoaded('phoneCountry')),
            'phone_number' => $this->phone_number,
            'roles' => $this->whenLoaded('roles'),
            'role_id' =>  $this->when($this->relationLoaded('roles'), function() {
                return $this->roles()->first();
            }),
            'vends' => VendResource::collection($this->whenLoaded('vends')),
        ];
    }
}
