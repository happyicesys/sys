<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\VendChannelResource;
use App\Traits\GetUserTimezone;

class VendResource extends JsonResource
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
        // $timezone = auth()->user()->has('operator') ? auth()->user()->operator->timezone : 'Asia/Singapore';

        return [
            'id' => $this->id,
            'code' => $this->code,
            'apkVerJson' => $this->apk_ver_json,
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
            'firmware_ver' => $this->firmware_ver ? dechex($this->firmware_ver) : null,
            'is_door_open' => $this->is_door_open ? 'Yes' : 'No',
            'is_online' => $this->is_online,
            'is_sensor_normal' => $this->is_sensor_normal ? 'Yes' : 'No',
            'is_temp_error' => $this->is_temp_error ? true : false,
            'last_invoice_date' => $this->when($this->relationLoaded('latestVendBinding'), function() {
                return ($this->latestVendBinding && $this->latestVendBinding->customer && $this->latestVendBinding->customer->last_invoice_date) ? Carbon::parse($this->latestVendBinding->customer->last_invoice_date)->setTimezone($this->getUserTimezone())->format('ymd') : null;
            }),
            'last_invoice_diff' => $this->when($this->relationLoaded('latestVendBinding'), function() {
                return ($this->latestVendBinding && $this->latestVendBinding->customer && $this->latestVendBinding->customer->last_invoice_date) ? Carbon::parse($this->latestVendBinding->customer->last_invoice_date)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null;
            }),
            'parameterJson' => $this->parameter_json,
            'private_key' => $this->private_key,
            'productMapping' => ProductMappingResource::make($this->whenLoaded('productMapping')),
            'vendChannelsJson' => $this->vend_channels_json,
            'vendChannelErrorLogsJson' => $this->vend_channel_error_logs_json,
            'vendChannelTotalsJson' => $this->vend_channel_totals_json,
            'vendTransactionTotalsJson' => $this->vend_transaction_totals_json,
            'latestVendBinding' => VendBindingResource::make($this->whenLoaded('latestVendBinding')),
            'salesData' => [
                'today' => [
                    'sales' => $this->when($this->relationLoaded('vendSevenDaysTransactions'), function() {
                        return $this->vendSevenDaysTransactions
                                    ->where('transaction_datetime', '>=', Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay())
                                    ->where('transaction_datetime', '<=', Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay())
                                    ->sum('amount')/ 100;
                    }),
                    'count' => $this->when($this->relationLoaded('vendSevenDaysTransactions'), function() {
                        return $this->vendSevenDaysTransactions
                                    // ->where('transaction_datetime', Carbon::today()->toDateString())
                                    ->where('transaction_datetime', '>=', Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay())
                                    ->where('transaction_datetime', '<=', Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay())
                                    ->count();
                    }),
                ],
                'yesterday' => [
                    'sales' => $this->when($this->relationLoaded('vendSevenDaysTransactions'), function() {
                        return $this->vendSevenDaysTransactions
                                    ->where('transaction_datetime', '>=', Carbon::yesterday()->setTimezone($this->getUserTimezone())->startOfDay())
                                    ->where('transaction_datetime', '<=', Carbon::yesterday()->setTimezone($this->getUserTimezone())->endOfDay())
                                    ->sum('amount')/ 100;
                    }),
                    'count' => $this->when($this->relationLoaded('vendSevenDaysTransactions'), function() {
                        return $this->vendSevenDaysTransactions
                                    // ->where('transaction_datetime', Carbon::today()->toDateString())
                                    ->where('transaction_datetime', '>=', Carbon::yesterday()->setTimezone($this->getUserTimezone())->startOfDay())
                                    ->where('transaction_datetime', '<=', Carbon::yesterday()->setTimezone($this->getUserTimezone())->endOfDay())
                                    ->count();
                    }),
                ],
                'sevenDays' => [
                    'sales' => $this->when($this->relationLoaded('vendSevenDaysTransactions'), function() {
                        return $this->vendSevenDaysTransactions->sum('amount')/ 100;
                    }),
                    'count' => $this->when($this->relationLoaded('vendSevenDaysTransactions'), function() {
                        return $this->vendSevenDaysTransactions->count();
                    }),
                ],
                'thirtyDays' => [
                    'sales' => $this->when($this->relationLoaded('vendThirtyDaysTransactions'), function() {
                        return $this->vendThirtyDaysTransactions->sum('amount')/ 100;
                    }),
                    'count' => $this->when($this->relationLoaded('vendThirtyDaysTransactions'), function() {
                        return $this->vendThirtyDaysTransactions->count();
                    }),
                ]
            ]
        ];
    }

    public function only(...$attributes)
    {
        return collect($this->resolve())
            ->only($attributes)
            ->toArray();
    }
}
