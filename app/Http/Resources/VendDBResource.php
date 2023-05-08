<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\GetUserTimezone;

class VendDBResource extends JsonResource
{
    use GetUserTimezone;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'apkVerJson' => json_decode($this->apk_ver_json),
            'serial_num' => $this->serial_num,
            'last_updated_at' => $this->last_updated_at ? Carbon::parse($this->last_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'name' => $this->name,
            'customer_code' => $this->customer_code,
            'customer_name' => $this->customer_name,
            'full_name' => $this->customer_code ? $this->customer_code . ' - ' . $this->customer_name : $this->name,
            'temp' => $this->temp/ 10,
            'temp_updated_at' => $this->temp_updated_at ? Carbon::parse($this->temp_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'coin_amount' => $this->coin_amount/ 100,
            'firmware_ver' => $this->firmware_ver ? dechex($this->firmware_ver) : null,
            'is_door_open' => $this->is_door_open ? 'Yes' : 'No',
            'is_online' => $this->is_online,
            'is_sensor_normal' => $this->is_sensor_normal ? 'Yes' : 'No',
            'is_temp_error' => $this->is_temp_error ? true : false,
            'last_invoice_date' => $this->last_invoice_date ? Carbon::parse($this->last_invoice_date)->setTimezone($this->getUserTimezone())->format('ymd') : null,
            'last_invoice_diff' => $this->last_invoice_date ? Carbon::parse($this->last_invoice_date)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'location_type_id' => $this->location_type_id,
            'location_type_name' => $this->location_type_name,
            'parameterJson' => json_decode($this->parameter_json),
            'postcode' => $this->postcode,
            'product_mapping_name' => $this->product_mapping_name,
            'product_mapping_remarks' => $this->product_mapping_remarks,
            'private_key' => $this->private_key,
            'vendChannelsJson' => json_decode($this->vend_channels_json),
            'vendChannelErrorLogsJson' => json_decode($this->vend_channel_error_logs_json),
            'vendChannelTotalsJson' => json_decode($this->vend_channel_totals_json),
            'vendTransactionTotalsJson' => json_decode($this->vend_transaction_totals_json),
        ];
    }
}
