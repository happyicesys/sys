<?php

namespace App\Http\Resources\DCVend;

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
            'created_at' => $this->created_at,
            'full_url' => $this->full_url,
            'type' => $this->type,
            'sequence' => $this->sequence,
        ];
    }
}
