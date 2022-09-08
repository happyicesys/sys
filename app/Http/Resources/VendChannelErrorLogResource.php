<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VendChannelErrorLogResource extends JsonResource
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
            'vend_channel_error' => new VendChannelErrorResource($this->vendChannelError),
            'is_error_cleared' => $this->is_error_cleared ? true : false,
        ];
    }
}
