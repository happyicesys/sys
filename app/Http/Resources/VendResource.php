<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\VendChannelResource;

class VendResource extends JsonResource
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
            'temp_updated_at' => $this->temp_updated_at ? Carbon::parse($this->temp_updated_at)->diffForHumans() : null,
            'coin_amount' => $this->coin_amount/ 100,
            'firmware_ver' => $this->firmware_ver ? dechex($this->firmware_ver) : null,
            'is_door_open' => $this->is_door_open ? 'Yes' : 'No',
            'is_sensor_normal' => $this->is_sensor_normal ? 'Yes' : 'No',
            'is_temp_error' => $this->is_temp_error ? true : false,
            'vendChannels' => VendChannelResource::collection($this->whenLoaded('vendChannels')),
            'vendChannelsJson' => $this->vend_channels_json,
            'vendChannelErrorLogsJson' => $this->vend_channel_error_logs_json,
            'vendChannelTotalsJson' => $this->vend_channel_totals_json,
            'latestVendBinding' => VendBindingResource::make($this->whenLoaded('latestVendBinding')),
            'vendChannelsTotals' => $this->when($this->relationLoaded('vendChannels'), function() {
                $total = [
                    'vendChannelsTotalQty' => $this->vendChannelsTotalQty,
                    'vendChannelsTotalCapacity' => $this->vendChannelsTotalCapacity,
                    'vendChannelsOutOfStock' => $this->vendChannelsOutOfStock,
                    'vendChannelsErrorLogsActive' => $this->vendChannelsErrorLogsActive,
                    'vendChannelsCount' => $this->vendChannelsCount,
                ];
                return [
                    'qty' => $total['vendChannelsTotalQty'],
                    'capacity' => $total['vendChannelsTotalCapacity'],
                    'sales' => $total['vendChannelsTotalCapacity'] - $total['vendChannelsTotalQty'],
                    'balancePercent' => $total['vendChannelsTotalCapacity'] ? round($total['vendChannelsTotalQty']/ $total['vendChannelsTotalCapacity'] * 100) : 0,
                    'outOfStock' => $total['vendChannelsOutOfStock'],
                    'activeErrorLogs' => $total['vendChannelsErrorLogsActive'],
                    'count' => $total['vendChannelsCount'],
                    'outOfStockSku' => $total['vendChannelsOutOfStock'] + $total['vendChannelsErrorLogsActive'],
                    'outOfStockSkuPercent' => $total['vendChannelsCount'] ? round(($total['vendChannelsOutOfStock'] + $total['vendChannelsErrorLogsActive'])/ $total['vendChannelsCount'] * 100) : 0,
                ];
            }),
        ];
    }

    public function only(...$attributes)
    {
        return collect($this->resolve())
            ->only($attributes)
            ->toArray();
    }
}
