<?php

namespace App\Http\Resources;

use Carbon\Carbon;
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
            'vendChannelError' => VendChannelErrorResource::make($this->vendChannelError),
            'is_error_cleared' => $this->is_error_cleared ? true : false,
            'created_at' => $this->created_at->format('ymd h:ia'),
        ];
    }
}
