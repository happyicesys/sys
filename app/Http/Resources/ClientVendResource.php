<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\GetUserTimezone;

class ClientVendResource extends JsonResource
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
            'code' => $this->code,
            'serial_num' => $this->serial_num,
            'last_updated_at' => $this->last_updated_at ? Carbon::parse($this->last_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'name' => $this->name,
            'full_name' => $this->code.$this->when($this->relationLoaded('latestVendBinding'), function() {
                return $this->latestVendBinding && $this->latestVendBinding->customer ? (' - '.$this->latestVendBinding->customer->code.' - '.$this->latestVendBinding->customer->name) : ($this->name ? ' - '.$this->name : '');
            }, function(){
                return $this->name ? ' - '.$this->name : '';
            }),
            'temp' => $this->temp/ 10,
            'temp_updated_at' => $this->temp_updated_at ? Carbon::parse($this->temp_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'coin_amount' => $this->coin_amount/ 100,
            'is_door_open' => $this->is_door_open ? 'Yes' : 'No',
            'is_online' => $this->is_online,
            'is_sensor_normal' => $this->is_sensor_normal ? 'Yes' : 'No',
            'is_temp_error' => $this->is_temp_error ? true : false,
            'vendTransactionTotalsJson' => $this->vend_transaction_totals_json,
            'vendChannels' => ClientVendChannelResource::collection($this->whenLoaded('vendChannels')),
        ];
    }
}
