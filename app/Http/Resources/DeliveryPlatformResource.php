<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryPlatformResource extends JsonResource
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
            'name' => $this->name,
            'country' => CountryResource::make($this->whenLoaded('country')),
            'default_access_method' => $this->default_access_method,
            'default_granted_type' => $this->default_granted_type,
            'default_scopes' => $this->default_scopes,
            'full_name' => $this->when($this->relationLoaded('country'), function() {
                return $this->country && $this->country->name ? $this->name.' ('.$this->country->name.')' : '';
            }, function(){
                return $this->name;
            }),
            'remarks' => $this->remarks,
            'field1_name' => $this->field1_name,
            'field2_name' => $this->field2_name,
            'field3_name' => $this->field3_name,
            'field4_name' => $this->field4_name,
        ];
    }
}
