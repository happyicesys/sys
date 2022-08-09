<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class VendingMachineResource extends JsonResource
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
            'code' => $this->code,
            'serial_num' => $this->serial_num,
            'name' => $this->name,
            'temp' => $this->temp/ 10,
            'temp_updated_at' => Carbon::parse($this->temp_updated_at)->diffForHumans(),
            'coin_amount' => $this->coin_amount/ 100,
            'firmware_ver' => $this->firmware_ver,
            'is_door_open' => $this->is_door_open ? true : false,
            'is_sensor_normal' => $this->is_sensor_normal ? true : false,
        ];
    }
}
