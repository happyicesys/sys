<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitCostResource extends JsonResource
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
            'cost' => $this->cost/ 100,
            'product' => $this->whenLoaded('product'),
            'profile' => $this->whenLoaded('profile'),
            'date_from' => $this->date_from ? Carbon::parse($this->date_from)->setTimezone($this->getUserTimezone())->format('Y-m-d') : null,
            'date_to' => $this->date_to ? Carbon::parse($this->date_to)->setTimezone($this->getUserTimezone())->format('Y-m-d') : null,
            'is_current' => $this->is_current,
        ];
    }
}
