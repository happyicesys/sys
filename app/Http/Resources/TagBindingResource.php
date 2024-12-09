<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagBindingResource extends JsonResource
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
            'tag_id' => $this->tag_id,
            'tag' => TagResource::make($this->whenLoaded('tag')),
            'modelable_id' => $this->modelable_id,
            'modelable_type' => $this->modelable_type,
        ];
    }
}
