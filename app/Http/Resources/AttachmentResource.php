<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
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
            'local_url' => $this->local_url,
            'full_url' => $this->full_url,
            'is_active' => $this->is_active,
            'type' => $this->type,
            'sequence' => $this->sequence,
            'name' => $this->name,
            'desc' => $this->desc,
        ];
    }
}
