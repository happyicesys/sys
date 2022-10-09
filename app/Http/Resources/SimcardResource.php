<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SimcardResource extends JsonResource
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
            'begin_date' => $this->begin_date,
            'code' => $this->code,
            'createdBy' => UserResource::make($this->whenLoaded('createdBy')),
            'created_by' => $this->created_by,
            'is_active' => $this->is_active,
            'phone_number' => $this->phone_number,
            'telco' => TelcoResource::make($this->whenLoaded('telco')),
            'telco_id' => $this->telco_id,
            'termination_date' => $this->termination_date,
            'updatedBy' => UserResource::make($this->whenLoaded('updatedBy')),
            'updated_by' => $this->updated_by,

        ];
    }
}
