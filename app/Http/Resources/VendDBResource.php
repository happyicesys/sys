<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\GetUserTimezone;

class VendDBResource extends JsonResource
{
    use GetUserTimezone;

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'account_manager_name' => isset($this->account_manager_name) ? $this->account_manager_name : null,
            'balance_percent' => isset($this->balance_percent) ? $this->balance_percent : null,
            'code' => $this->code,
            'amount_average_day' => isset($this->amount_average_day) ? $this->amount_average_day/100 : null,
            'apkVerJson' => isset($this->apk_ver_json) ? json_decode($this->apk_ver_json) : null,
            'begin_date' => isset($this->begin_date) ? Carbon::parse($this->begin_date)->setTimezone($this->getUserTimezone())->format('Y-m-d') : null,
            'begin_date_short' => isset($this->begin_date) ? Carbon::parse($this->begin_date)->setTimezone($this->getUserTimezone())->format('ymd') : null,
            'serial_num' => isset($this->serial_num) ? $this->serial_num : null,
            'last_updated_at' => isset($this->last_updated_at) ? Carbon::parse($this->last_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'mqtt_last_updated_at' => isset($this->mqtt_last_updated_at) ? Carbon::parse($this->mqtt_last_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'mqtt_updated_at' => isset($this->mqtt_updated_at) ? Carbon::parse($this->mqtt_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'name' => $this->name,
            'cms_invoice_history' => isset($this->cms_invoice_history) ? json_decode($this->cms_invoice_history) : null,
            'customer_code' => isset($this->customer_code) ? $this->customer_code : null,
            'customer_json' => isset($this->customer_json) ? json_decode($this->customer_json) : null,
            'customer_name' => isset($this->customer_name) ? $this->customer_name : null,
            'person_id' => isset($this->person_id) ? $this->person_id : null,
            'full_name' => isset($this->customer_code) ? $this->customer_code . ' - ' . $this->customer_name : $this->name,
            'cust_full_name' => isset($this->customer_code) ? $this->customer_code . ' - ' . $this->customer_name : $this->name,
            'temp' => isset($this->temp) ? $this->temp/ 10 : null,
            'temp_updated_at' => isset($this->temp_updated_at) ? Carbon::parse($this->temp_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'termination_date' => isset($this->termination_date) ? Carbon::parse($this->termination_date)->setTimezone($this->getUserTimezone())->format('Y-m-d') : null,
            'termination_date_short' => isset($this->termination_date) ? Carbon::parse($this->termination_date)->setTimezone($this->getUserTimezone())->format('ymd') : null,
            'coin_amount' => isset($this->coin_amount) ? $this->coin_amount/ 100 : null,
            'firmware_ver' => isset($this->firmware_ver) && $this->firmware_ver ? dechex($this->firmware_ver) : null,
            'is_active' => isset($this->is_active) && $this->is_active ? true : false,
            'vend_is_active' => isset($this->vend_is_active) && $this->vend_is_active ? true : false,
            'customer_is_active' => isset($this->customer_is_active) && $this->customer_is_active ? true : false,
            'is_door_open' => isset($this->is_door_open) && $this->is_door_open ? 'Yes' : 'No',
            'is_mqtt' => isset($this->is_mqtt) ? $this->is_mqtt : null,
            'is_mqtt_active' => isset($this->is_mqtt_active) && $this->is_mqtt_active ? true : false,
            'is_mqtt_offline_notified' => isset($this->is_mqtt_offline_notified) && $this->is_mqtt_offline_notified ? true : false,
            'is_online' => isset($this->is_online) ? $this->is_online : null,
            'is_sensor_normal' => isset($this->is_sensor_normal) && $this->is_sensor_normal ? 'Yes' : 'No',
            'is_temp_error' => isset($this->is_temp_error) && $this->is_temp_error ? true : false,
            'is_testing' => isset($this->is_testing) && $this->is_testing ? true : false,
            'last_invoice_date' => isset($this->last_invoice_date) ? Carbon::parse($this->last_invoice_date)->setTimezone($this->getUserTimezone())->format('ymd') : null,
            'last_invoice_diff' => isset($this->last_invoice_date) ? Carbon::parse($this->last_invoice_date)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'out_of_stock_sku_percent' => isset($this->out_of_stock_sku_percent) ? $this->out_of_stock_sku_percent : null,
            'next_invoice_date' => isset($this->next_invoice_date) ? Carbon::parse($this->next_invoice_date)->setTimezone($this->getUserTimezone())->format('ymd') : null,
            'next_invoice_diff' => isset($this->next_invoice_date) ? Carbon::parse($this->next_invoice_date)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'location_type_id' => isset($this->location_type_id) ? $this->location_type_id : null,
            'location_type_name' => isset($this->location_type_name) ? $this->location_type_name : null,
            'log_created_at' => isset($this->log_created_at) ? Carbon::parse($this->log_created_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'log_url' => isset($this->log_url) ? $this->log_url : null,
            'operator_name' => isset($this->operator_name) ? $this->operator_name : null,
            'parameterJson' => isset($this->parameter_json) ? json_decode($this->parameter_json) : null,
            'postcode' => isset($this->postcode) ? $this->postcode : null,
            'product_mapping_name' => isset($this->product_mapping_name) ? $this->product_mapping_name : null,
            'product_mapping_remarks' => isset($this->product_mapping_remarks) ? $this->product_mapping_remarks : null,
            'private_key' => isset($this->private_key) ? $this->private_key : null,
            'vendChannelsJson' => isset($this->vend_channels_json) ? json_decode($this->vend_channels_json) : null,
            'vendChannelErrorLogsJson' => isset($this->vend_channel_error_logs_json) ? json_decode($this->vend_channel_error_logs_json) : null,
            'vendChannelTotalsJson' => isset($this->vend_channel_totals_json) ? json_decode($this->vend_channel_totals_json) : null,
            'vendTransactionTotalsJson' => isset($this->vend_transaction_totals_json) ? json_decode($this->vend_transaction_totals_json) : null,
            'virtual_customer_code' => isset($this->virtual_customer_code) ? $this->virtual_customer_code : null,
            'virtual_customer_prefix' => isset($this->virtual_customer_prefix) ? $this->virtual_customer_prefix : null,
            'virtual_vend_records_thirty_days_amount_average' => isset($this->virtual_vend_records_thirty_days_amount_average) ? $this->virtual_vend_records_thirty_days_amount_average/100 : 0,
            'this_month_count' => isset($this->this_month_count) ? $this->this_month_count : 0,
            'this_month_revenue' => isset($this->this_month_revenue) ? $this->this_month_revenue/100 : 0,
            'this_month_gross_profit' => isset($this->this_month_gross_profit) ? $this->this_month_gross_profit/100 : 0,
            'this_month_gross_profit_margin' => isset($this->this_month_gross_profit_margin) ? $this->this_month_gross_profit_margin : 0,
            'last_month_count' => isset($this->last_month_count) ? $this->last_month_count : 0,
            'last_month_revenue' => isset($this->last_month_revenue) ? $this->last_month_revenue/100 : 0,
            'last_month_gross_profit' => isset($this->last_month_gross_profit) ? $this->last_month_gross_profit/100 : 0,
            'last_month_gross_profit_margin' => isset($this->last_month_gross_profit_margin) ? $this->last_month_gross_profit_margin : 0,
            'last_two_month_count' => isset($this->last_two_month_count) ? $this->last_two_month_count : 0,
            'last_two_month_revenue' => isset($this->last_two_month_revenue) ? $this->last_two_month_revenue/100 : 0,
            'last_two_month_gross_profit' => isset($this->last_two_month_gross_profit) ? $this->last_two_month_gross_profit/100 : 0,
            'last_two_month_gross_profit_margin' => isset($this->last_two_month_gross_profit_margin) ? $this->last_two_month_gross_profit_margin : 0,
        ];
    }
}
