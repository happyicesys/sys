<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VendingMachineChannelErrorLogResource extends JsonResource
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
            'vending_machine_channel_error' => new VendingMachineChannelErrorResource($this->vendingMachineChannelError),
            'is_error_cleared' => $this->is_error_cleared ? true : false,
        ];
    }
}
