<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'category_group_id' => CategoryGroupResource::make($this->whenLoaded('categoryGroup')),
            'classname' => $this->classname,
            'desc' => $this->desc,
            'name' => $this->name,
            'remarks' => $this->remarks,
            'sequence' => $this->sequence,
            'type' => $this->type,
            'categoryGroup' => CategoryGroupResource::make($this->whenLoaded('categoryGroup')),
        ];
    }
}
