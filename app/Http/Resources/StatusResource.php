<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return  [
            'id' => $this->id,
            'classname' => $this->classname,
            'desc' => $this->desc,
            'is_active' => $this->is_active,
            'name' => $this->name,
            'sequence' => $this->sequence,
            'type' => $this->type,
        ];
    }
}
