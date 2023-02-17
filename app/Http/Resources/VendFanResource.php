<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\GetUserTimezone;

class VendFanResource extends JsonResource
{
    use GetUserTimezone;
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'value' => $this->value,
            'created_at' => Carbon::parse($this->created_at)->setTimezone($this->getUserTimezone())->format('Y-m-d\TH:i:sP'),
            'type' => $this->type,
        ];
    }
}
