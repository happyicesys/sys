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
            'full_name' => $this->when($this->relationLoaded('country'), function() {
                return $this->country && $this->country->name ? $this->name.' ('.$this->country->name.')' : '';
            }, function(){
                return $this->name;
            }),
            'remarks' => $this->remarks,
            'field1' => $this->field1,
            'field2' => $this->field2,
            'field3' => $this->field3,
        ];
    }
}
