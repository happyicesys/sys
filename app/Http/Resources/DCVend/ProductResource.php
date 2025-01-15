<?php

namespace App\Http\Resources\DCVend;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'full_name' => $this->code.' - '.$this->name,
            'desc' => $this->desc,
            'is_available' => $this->is_available ? true : false,
            'is_inventory' => $this->is_inventory ? true : false,
            'thumbnail' => AttachmentResource::make($this->whenLoaded('thumbnail')),
            'translated_names_json' => $this->translated_names_json,
        ];
    }
}
