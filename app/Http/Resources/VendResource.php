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
            'begin_date' => isset($this->begin_date) ? Carbon::parse($this->begin_date)->setTimezone($this->getUserTimezone())->format('Y-m-d') : null,
            'begin_date_short' => isset($this->begin_date) ? Carbon::parse($this->begin_date)->setTimezone($this->getUserTimezone())->format('ymd') : null,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'serial_num' => $this->serial_num,
            'last_updated_at' => $this->last_updated_at ? Carbon::parse($this->last_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'logs' => AttachmentResource::collection($this->whenLoaded('logs')),
            'name' => $this->name,
            'full_name' => $this->when($this->relationLoaded('customer'), function() {
                return $this->customer ?
                    ($this->customer->virtual_customer_code ?
                    $this->code . ' - '. $this->customer->virtual_customer_code . ' (' . $this->customer->virtual_customer_prefix . ') - ' . $this->customer->name :
                    $this->code . ' - ' . $this->customer->code.' - '.$this->customer->name)
                : ($this->name ? $this->code . ' - ' . $this->name : '');
            }, function(){
                return $this->code ? ' - '.$this->name : '';
            }),
            'cust_full_name' => $this->when($this->relationLoaded('customer'), function() {
                return $this->customer ?
                    ($this->customer->virtual_customer_code ?
                    $this->code . ' - '. $this->customer->virtual_customer_code . ' (' . $this->customer->virtual_customer_prefix . ') - ' . $this->customer->name :
                    $this->code . ' - ' . $this->customer->code.' - '.$this->customer->name)
                : ($this->name ? $this->code . ' - ' . $this->name : '');
            }, function(){
                return $this->code ? ' - '.$this->name : '';
            }),
            'temp' => $this->temp/ 10,
            'temp_updated_at' => $this->temp_updated_at ? Carbon::parse($this->temp_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'termination_date' => isset($this->termination_date) ? Carbon::parse($this->termination_date)->setTimezone($this->getUserTimezone())->format('Y-m-d') : null,
            'termination_date_short' => isset($this->termination_date) ? Carbon::parse($this->termination_date)->setTimezone($this->getUserTimezone())->format('ymd') : null,
            'coin_amount' => $this->coin_amount/ 100,
            'firmware_ver' => $this->firmware_ver ? dechex($this->firmware_ver) : null,
            'is_active' => $this->is_active ? true : false,
            'is_door_open' => $this->is_door_open ? 'Yes' : 'No',
            'is_online' => $this->is_online,
            'is_sensor_normal' => $this->is_sensor_normal ? 'Yes' : 'No',
            'is_temp_error' => $this->is_temp_error ? true : false,
            'is_testing' => $this->is_testing ? true : false,
            'last_invoice_date' => $this->when($this->relationLoaded('customer'), function() {
                return ($this->customer && $this->customer->last_invoice_date) ? Carbon::parse($this->customer->last_invoice_date)->setTimezone($this->getUserTimezone())->format('ymd') : null;
            }),
            'last_invoice_diff' => $this->when($this->relationLoaded('customer'), function() {
                return ($this->customer && $this->customer->last_invoice_date) ? Carbon::parse($this->customer->last_invoice_date)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null;
            }),
            'locationType' => $this->when($this->relationLoaded('locationType'), function() {
                return ($this->customer && $this->customer->locationType) ? $this->customer->locationType : null;
            }),
            'location_type_id' => $this->location_type_id,
            'location_type_name' => $this->location_type_name,
            'parameterJson' => $this->parameter_json,
            'pivot' => isset($this->pivot) ? $this->pivot : null,
            'private_key' => $this->private_key,
            'productMapping' => ProductMappingResource::make($this->whenLoaded('productMapping')),
            'vendChannelsJson' => $this->vend_channels_json,
            'vendChannelErrorLogsJson' => $this->vend_channel_error_logs_json,
            'vendChannelTotalsJson' => $this->vend_channel_totals_json,
            'vendSnapshots' => VendSnapshotDBResource::collection($this->whenLoaded('vendSnapshots')),
            'vendTransactionTotalsJson' => $this->vend_transaction_totals_json,
            'this_month_count' => $this->this_month_count,
            'this_month_revenue' => $this->this_month_revenue/100,
            'this_month_gross_profit' => $this->this_month_gross_profit/100,
            'this_month_gross_profit_margin' => $this->this_month_gross_profit_margin,
            'last_month_count' => $this->last_month_count,
            'last_month_revenue' => $this->last_month_revenue/100,
            'last_month_gross_profit' => $this->last_month_gross_profit/100,
            'last_month_gross_profit_margin' => $this->last_month_gross_profit_margin,
            'last_two_month_count' => $this->last_two_month_count,
            'last_two_month_revenue' => $this->last_two_month_revenue/100,
            'last_two_month_gross_profit' => $this->last_two_month_gross_profit/100,
            'last_two_month_gross_profit_margin' => $this->last_two_month_gross_profit_margin,
        ];
    }

    public function only(...$attributes)
    {
        return collect($this->resolve())
            ->only($attributes)
            ->toArray();
    }
}
