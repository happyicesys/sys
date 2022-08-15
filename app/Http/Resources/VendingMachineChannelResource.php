<?php

namespace App\Http\Resources;

use App\Http\Resources\VendingMachineChannelErrorLogResource;
use Illuminate\Http\Resources\Json\JsonResource;

class VendingMachineChannelResource extends JsonResource
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
            'qty' => $this->qty,
            'capacity' => $this->capacity,
            'amount' => $this->amount,
            'is_active' => $this->is_active ? true : false,
            'vending_machine_channel_error_logs' => VendingMachineChannelErrorLogResource::collection($this->vendingMachineChannelErrorLogs),

        ];
    }
}
