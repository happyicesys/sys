<?php

namespace App\Http\Resources;

use App\Models\Customer;
use App\Models\Vend;
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
            'account_manager_name' => isset($this->account_manager_name) ? $this->account_manager_name : null,
            'actual_stock_in_value' => isset($this->actual_stock_in_value) ? $this->actual_stock_in_value / 100 : null,
            'actual_stock_in_qty' => isset($this->actual_stock_in_qty) ? $this->actual_stock_in_qty : null,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'balance_percent' => isset($this->balance_percent) ? $this->balance_percent : null,
            'cashlessTerminal' => CashlessTerminalResource::make($this->whenLoaded('cashlessTerminal')),
            'cashless_terminal_id' => isset($this->cashless_terminal_id) ? $this->cashless_terminal_id : null,
            'claw_machine_board_id' => isset($this->claw_machine_board_id) ? $this->claw_machine_board_id : null,
            'claw_machine_body_id' => isset($this->claw_machine_body_id) ? $this->claw_machine_body_id : null,
            'code' => $this->code,
            'deliveryAddress' => AddressResource::make($this->whenLoaded('deliveryAddress')),
            'delivery_platform_slug' => isset($this->delivery_platform_slug) ? $this->delivery_platform_slug : null,
            'acbVmcPaJson' => isset($this->acb_vmc_pa_json) ? $this->acb_vmc_pa_json : null,
            'amount_average_day' => isset($this->amount_average_day) ? $this->amount_average_day / 100 : null,
            'apkVerJson' => isset($this->apk_ver_json) ? $this->apk_ver_json : null,
            'begin_date' => isset($this->begin_date) ? Carbon::parse($this->begin_date)->setTimezone($this->getUserTimezone())->format('Y-m-d') : null,
            'begin_date_short' => isset($this->begin_date) ? Carbon::parse($this->begin_date)->setTimezone($this->getUserTimezone())->format('ymd') : null,
            'binded_at' => isset($this->binded_at) ? Carbon::parse($this->binded_at)->setTimezone($this->getUserTimezone())->toDateTimeString() : null,
            'deliveryProductMappingVends' => DeliveryProductMappingVendResource::collection($this->whenLoaded('deliveryProductMappingVends')),
            'customerVendBindings' => CustomerVendBindingResource::collection($this->whenLoaded('customerVendBindings')),
            // 'is_using_server_price' => $this->is_using_server_price,
            'serial_num' => isset($this->serial_num) ? $this->serial_num : null,
            'server_price_type' => isset($this->server_price_type) ? $this->server_price_type : null,
            // compare last_updated_at and mqtt_last_updated_at which time is nearer to current time, then show the shortRelativeDiffForHumans
            'label_name' => isset($this->label_name) ? $this->label_name : null,
            'last_ops_job_acc_total_amount' => isset($this->last_ops_job_acc_total_amount) ? $this->last_ops_job_acc_total_amount / 100 : 0,
            'last_ops_job_acc_total_count' => isset($this->last_ops_job_acc_total_count) ? $this->last_ops_job_acc_total_count : 0,
            'last_second_ops_job_acc_total_amount' => isset($this->last_second_ops_job_acc_total_amount) ? $this->last_second_ops_job_acc_total_amount / 100 : 0,
            'last_second_ops_job_acc_total_count' => isset($this->last_second_ops_job_acc_total_count) ? $this->last_second_ops_job_acc_total_count : 0,
            // 'last_online_at' => isset($this->last_updated_at) || isset($this->mqtt_last_updated_at)
            // ? $this->getNearestTime($this->last_updated_at, $this->mqtt_last_updated_at)->shortRelativeDiffForHumans()
            // : null,
            'last_online_at' => isset($this->last_updated_at)
                ? $this->last_updated_at->shortRelativeDiffForHumans()
                : null,
            'last_thirty_days_stock_in_amount' => isset($this->last_thirty_days_stock_in_amount) ? $this->last_thirty_days_stock_in_amount / 100 : 0,
            'last_thirty_days_stock_in_qty' => isset($this->last_thirty_days_stock_in_qty) ? $this->last_thirty_days_stock_in_qty : 0,
            'thirty_days_stock_in_delta_amount' => isset($this->thirty_days_stock_in_delta_amount) ? $this->thirty_days_stock_in_delta_amount : 0,
            'thirty_days_stock_in_delta_percent' => isset($this->thirty_days_stock_in_delta_percent) ? $this->thirty_days_stock_in_delta_percent : 0,
            'last_updated_at' => isset($this->last_updated_at) ? Carbon::parse($this->last_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'lcd_monitor' => isset($this->lcd_monitor_id) ? Vend::LCD_MONITOR_MAPPINGS[$this->lcd_monitor_id] : null,
            'lcd_monitor_id' => isset($this->lcd_monitor_id) ? $this->lcd_monitor_id : null,
            'menu_frame_id' => isset($this->menu_frame_id) ? $this->menu_frame_id : null,
            'modemType' => ModemTypeResource::make($this->whenLoaded('modemType')),
            'modem_type_id' => isset($this->modem_type_id) ? $this->modem_type_id : null,
            'modem_type_name' => isset($this->modem_type_name) ? $this->modem_type_name : null,
            'modem_type_is_resettable' => isset($this->modem_type_is_resettable) ? $this->modem_type_is_resettable : null,
            'modemUnit' => ModemUnitResource::make($this->whenLoaded('modemUnit')),
            'modem_unit_id' => isset($this->modem_unit_id) ? $this->modem_unit_id : null,
            'modem_unit_imei' => isset($this->modem_unit_imei) ? $this->modem_unit_imei : null,
            'modem_unit_is_online' => isset($this->modem_unit_is_online) ? $this->modem_unit_is_online : null,
            'modem_unit_last_updated_at' => isset($this->modem_unit_last_updated_at) ? Carbon::parse($this->modem_unit_last_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'mqtt_last_updated_at' => isset($this->mqtt_last_updated_at) ? Carbon::parse($this->mqtt_last_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'mqtt_updated_at' => isset($this->mqtt_updated_at) ? Carbon::parse($this->mqtt_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'name' => isset($this->name) ? $this->name : null,
            'cms_invoice_history' => isset($this->cms_invoice_history) ? $this->cms_invoice_history : null,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'customer_code' => isset($this->customer_code) ? $this->customer_code : null,
            'customer_id' => isset($this->customer_id) ? $this->customer_id : null,
            'customer_name' => isset($this->customer_name) ? $this->customer_name : null,
            'frequency_per_week_status' => isset($this->frequency_per_week_status) ? $this->frequency_per_week_status : null,
            'frequency_per_week_status_name' => isset($this->frequency_per_week_status) ? Customer::FREQUENCY_PER_WEEK_STATUSES_MAPPING[$this->frequency_per_week_status] : null,
            'full_name' => $this->when($this->relationLoaded('customer'), function () {
                if ($this->customer && $this->customer->person_id) {
                    return '(' . $this->code . ') ' . $this->customer->virtual_customer_code . ' - ' . $this->customer->name;
                } else if ($this->customer && !$this->customer->person_id) {
                    return '(' . $this->code . ') ' . $this->customer->id + 20000 . ' - ' . $this->customer->name;
                } else {
                    return $this->code;
                }
            }),
            'cust_full_name' => $this->when($this->relationLoaded('customer'), function () {
                if ($this->customer && $this->customer->person_id) {
                    return '(' . $this->code . ')  - ' . $this->customer->virtual_customer_code . ' - ' . $this->customer->name;
                } else if ($this->customer && !$this->customer->person_id) {
                    return '(' . $this->code . ') ' . $this->customer->code . ' - ' . $this->customer->name;
                } else {
                    return '(' . $this->code . ')' . ' - ' . $this->label_name;
                }
            }),
            'key' => KeyResource::make($this->whenLoaded('key')),
            'key_name' => isset($this->key_name) ? $this->key_name : null,
            'person_id' => isset($this->person_id) ? $this->person_id : null,
            'temp' => isset($this->temp) ? ((int) $this->temp) / 10 : null,
            't1_lowest_48h' => isset($this->t1_lowest_48h) ? ((int) $this->t1_lowest_48h) / 10 : null,
            'temp_updated_at' => isset($this->temp_updated_at) ? Carbon::parse($this->temp_updated_at)->setTimezone($this->getUserTimezone())->diffForHumans() : null,
            'termination_date' => isset($this->termination_date) ? Carbon::parse($this->termination_date)->setTimezone($this->getUserTimezone())->format('Y-m-d') : null,
            'termination_date_short' => isset($this->termination_date) ? Carbon::parse($this->termination_date)->setTimezone($this->getUserTimezone())->format('ymd') : null,
            'is_enable_grab_collection' => $this->is_enable_grab_collection,
            'is_enable_soft_keyboard_qr_pay' => $this->is_enable_soft_keyboard_qr_pay,
            'is_enable_soft_keyboard_cash_pay' => $this->is_enable_soft_keyboard_cash_pay,
            'is_enable_soft_keyboard_credit_card_pay' => $this->is_enable_soft_keyboard_credit_card_pay,
            'has_display_screen' => $this->has_display_screen,
            'coin_amount' => isset($this->coin_amount) ? $this->coin_amount / 100 : null,
            'firmware_ver' => isset($this->firmware_ver) && $this->firmware_ver ? dechex($this->firmware_ver) : null,
            'is_active' => isset($this->is_active) && $this->is_active ? true : false,
            'vend_is_active' => isset($this->vend_is_active) && $this->vend_is_active ? true : false,
            'customer_is_active' => isset($this->customer_is_active) && $this->customer_is_active ? true : false,
            'is_door_open' => isset($this->is_door_open) && $this->is_door_open ? 'Yes' : 'No',
            'is_disposed' => isset($this->is_disposed) ? $this->is_disposed : null,
            'is_sold' => isset($this->is_sold) ? $this->is_sold : false,
            'is_mqtt' => isset($this->is_mqtt) ? $this->is_mqtt : null,
            'is_mqtt_active' => isset($this->is_mqtt_active) && $this->is_mqtt_active ? true : false,
            'is_mqtt_offline_notified' => isset($this->is_mqtt_offline_notified) && $this->is_mqtt_offline_notified ? true : false,
            'is_online' => isset($this->is_online) ? $this->is_online : null,
            'is_sensor_normal' => isset($this->is_sensor_normal) && $this->is_sensor_normal ? 'Yes' : 'No',
            'is_temp_active' => isset($this->is_temp_active) && $this->is_temp_active ? true : false,
            'is_temp_error' => isset($this->is_temp_error) && $this->is_temp_error ? true : false,
            'is_testing' => isset($this->is_testing) && $this->is_testing ? true : false,
            'is_fan_enabled' => isset($this->is_fan_enabled) ? ($this->is_fan_enabled === 0 || $this->is_fan_enabled === false ? false : true) : true,
            'key_id' => isset($this->key_id) ? $this->key_id : null,
            'last_invoice_date' => isset($this->last_invoice_date) ? Carbon::parse($this->last_invoice_date)->setTimezone($this->getUserTimezone())->format('ymd') : null,
            'last_invoice_diff' => isset($this->last_invoice_date) ? Carbon::parse($this->last_invoice_date)->setTimezone($this->getUserTimezone())->startOfDay()->diffForHumans(['options' => Carbon::ONE_DAY_WORDS]) : null,
            'last_invoice_diff_count' => isset($this->last_invoice_date) ? Carbon::parse($this->last_invoice_date)->setTimezone($this->getUserTimezone())->startOfDay()->diffInDays() : null,
            'out_of_stock_sku_percent' => isset($this->out_of_stock_sku_percent) ? $this->out_of_stock_sku_percent : null,
            'next_invoice_date' => isset($this->next_invoice_date) ? Carbon::parse($this->next_invoice_date)->setTimezone($this->getUserTimezone())->format('ymd') : null,
            'next_invoice_diff' => isset($this->next_invoice_date)
                ? (
                    (
                        Carbon::parse($this->next_invoice_date)->setTimezone($this->getUserTimezone())->diffInDays() > 0
                        && Carbon::parse($this->next_invoice_date)->setTimezone($this->getUserTimezone())->diffInDays() < 1
                    )
                    ? 'today'
                    : (
                        (
                            Carbon::parse($this->next_invoice_date)->setTimezone($this->getUserTimezone())->diffInDays() > -1
                            && Carbon::parse($this->next_invoice_date)->setTimezone($this->getUserTimezone())->diffInDays() < 0
                        )
                        ? 'tomorrow'
                        : (Carbon::parse($this->next_invoice_date)->setTimezone($this->getUserTimezone())->diffInDays() < 0 ? ('Next ' . ceil(abs(Carbon::parse($this->next_invoice_date)->setTimezone($this->getUserTimezone())->diffInDays())) . ' days') : ((Carbon::parse($this->next_invoice_date)->setTimezone($this->getUserTimezone())->diffInDays() > 1 && Carbon::parse($this->next_invoice_date)->setTimezone($this->getUserTimezone())->diffInDays() < 2) ? 'yesterday' : ('Last ' . ceil(abs(Carbon::parse($this->next_invoice_date)->setTimezone($this->getUserTimezone())->diffInDays())) - 1 . ' days')))
                    )
                )
                : null,
            'next_invoice_diff_count' => isset($this->next_invoice_date) ? Carbon::parse($this->next_invoice_date)->setTimezone($this->getUserTimezone())->diffInDays() : null,
            'next_invoice_driver_id' => isset($this->next_invoice_driver_id) ? $this->next_invoice_driver_id : null,
            'lastOpsJobItem' => OpsJobItemResource::make($this->whenLoaded('lastOpsJobItem')),
            'lastSecondOpsJobItem' => OpsJobItemResource::make($this->whenLoaded('lastSecondOpsJobItem')),
            'last_ops_job_amount' => isset($this->last_ops_job_amount) ? $this->last_ops_job_amount / 100 : null,
            'last_ops_job_cash_amount' => isset($this->last_ops_job_cash_amount) ? $this->last_ops_job_cash_amount / 100 : null,
            'last_ops_job_count' => isset($this->last_ops_job_count) ? $this->last_ops_job_count : null,
            'last_second_ops_job_amount' => isset($this->last_second_ops_job_amount) ? $this->last_second_ops_job_amount / 100 : null,
            'last_second_ops_job_cash_amount' => isset($this->last_second_ops_job_cash_amount) ? $this->last_second_ops_job_cash_amount / 100 : null,
            'last_second_ops_job_count' => isset($this->last_second_ops_job_count) ? $this->last_second_ops_job_count : null,
            'location_type_id' => isset($this->location_type_id) ? $this->location_type_id : null,
            'location_type_name' => isset($this->location_type_name) ? $this->location_type_name : null,
            'log_created_at' => isset($this->log_created_at) ? Carbon::parse($this->log_created_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'log_url' => isset($this->log_url) ? $this->log_url : null,
            'nextOpsJobItem' => OpsJobItemResource::make($this->whenLoaded('nextOpsJobItem')),
            'next_ops_job_amount' => isset($this->next_ops_job_amount) ? $this->next_ops_job_amount / 100 : null,
            'next_ops_job_cash_amount' => isset($this->next_ops_job_cash_amount) ? $this->next_ops_job_cash_amount / 100 : null,
            'next_ops_job_count' => isset($this->next_ops_job_count) ? $this->next_ops_job_count : null,
            'operator_id' => isset($this->operator_id) ? $this->operator_id : null,
            'operator_code' => isset($this->operator_code) ? $this->operator_code : null,
            'operator_name' => isset($this->operator_name) ? $this->operator_name : null,
            'ops_note' => isset($this->ops_note) ? $this->ops_note : null,
            'parameterJson' => isset($this->parameter_json) ? $this->parameter_json : null,
            'postcode' => isset($this->postcode) ? $this->postcode : null,
            'preferred_visit_days_json' => isset($this->preferred_visit_days_json) ? $this->preferred_visit_days_json : null,
            'productMapping' => ProductMappingResource::make($this->whenLoaded('productMapping')),
            'product_mapping_id' => isset($this->product_mapping_id) ? $this->product_mapping_id : null,
            'product_mapping_name' => isset($this->product_mapping_name) ? $this->product_mapping_name : null,
            'product_mapping_remarks' => isset($this->product_mapping_remarks) ? $this->product_mapping_remarks : null,
            'private_key' => isset($this->private_key) ? $this->private_key : null,
            'simcard' => SimcardResource::make($this->whenLoaded('simcard')),
            'simcard_id' => isset($this->simcard_id) ? $this->simcard_id : null,
            'selling_price_type' => isset($this->selling_price_type) ? $this->selling_price_type : null,
            'settings_parameter_json' => isset($this->settings_parameter_json) ? $this->settings_parameter_json : null,
            'thirty_days_over_full_load_ratio' => isset($this->thirty_days_over_full_load_ratio) ? $this->thirty_days_over_full_load_ratio : 0,
            'total_full_load_amount' => isset($this->total_full_load_amount) ? $this->total_full_load_amount / 100 : null,
            'total_ops_job_stock_amount' => isset($this->total_ops_job_stock_amount) ? $this->total_ops_job_stock_amount / 100 : null,
            'total_ops_job_stock_cost' => isset($this->total_ops_job_stock_cost) ? $this->total_ops_job_stock_cost / 100 : null,

            'total_stock_cost' => isset($this->total_stock_cost) ? $this->total_stock_cost / 100 : null,
            'total_stock_amount' => isset($this->total_stock_amount) ? $this->total_stock_amount / 100 : null,
            'upcomingProductMapping' => ProductMappingResource::make($this->whenLoaded('upcomingProductMapping')),
            'upcoming_product_mapping_id' => isset($this->upcoming_product_mapping_id) ? $this->upcoming_product_mapping_id : null,
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'vendChannels' => VendChannelResource::collection($this->whenLoaded('vendChannels')),
            'vendChannelsJson' => isset($this->vend_channels_json) ? $this->vend_channels_json : null,
            'vendChannelErrorLogsJson' => isset($this->vend_channel_error_logs_json) ? $this->vend_channel_error_logs_json : null,
            'vendChannelTotalsJson' => isset($this->vend_channel_totals_json) ? $this->vend_channel_totals_json : null,
            'vendConfig' => VendConfigResource::make($this->whenLoaded('vendConfig')),
            'vendContract' => VendContractResource::make($this->whenLoaded('vendContract')),
            'vend_contract_id' => isset($this->vend_contract_id) ? $this->vend_contract_id : null,
            'vendModel' => VendModelResource::make($this->whenLoaded('vendModel')),
            'vendPrefix' => VendPrefixResource::make($this->whenLoaded('vendPrefix')),
            'vend_prefix_id' => isset($this->vend_prefix_id) ? $this->vend_prefix_id : null,
            'vend_prefix_name' => isset($this->vend_prefix_name) ? $this->vend_prefix_name : null,
            'vend_config_name' => isset($this->vend_config_name) ? $this->vend_config_name : null,
            'vendSerialNumber' => VendSerialNumberResource::make($this->whenLoaded('vendSerialNumber')),
            'vend_serial_number_id' => isset($this->vend_serial_number_id) ? $this->vend_serial_number_id : null,
            'vend_serial_number_code' => isset($this->vend_serial_number_code) ? $this->vend_serial_number_code : null,
            'vend_vend_config_version' => $this->vend_vend_config_version,
            'vendThreeDaysErrorTransactions' => VendTransactionResource::collection($this->whenLoaded('vendThreeDaysErrorTransactions')),
            'vendTransactionTotalsJson' => isset($this->vend_transaction_totals_json) ? $this->vend_transaction_totals_json : null,
            'vendSevenDaysErrorTransactions' => VendTransactionResource::collection($this->whenLoaded('vendSevenDaysErrorTransactions')),
            'vend_id' => isset($this->vend_id) ? $this->vend_id : null,
            'virtual_customer_code' => isset($this->virtual_customer_code) ? $this->virtual_customer_code : null,
            'virtual_customer_prefix' => isset($this->virtual_customer_prefix) ? $this->virtual_customer_prefix : null,
            'virtual_vend_records_thirty_days_amount_average' => isset($this->virtual_vend_records_thirty_days_amount_average) ? $this->virtual_vend_records_thirty_days_amount_average / 100 : 0,
            'zone_name' => isset($this->zone_name) ? $this->zone_name : null,
            'zone_id' => isset($this->zone_id) ? $this->zone_id : null,
            'this_month_count' => isset($this->this_month_count) ? $this->this_month_count : 0,
            'this_month_revenue' => isset($this->this_month_revenue) ? $this->this_month_revenue / 100 : 0,
            'this_month_gross_profit' => isset($this->this_month_gross_profit) ? $this->this_month_gross_profit / 100 : 0,
            'this_month_gross_profit_margin' => isset($this->this_month_gross_profit_margin) ? $this->this_month_gross_profit_margin : 0,
            'last_month_count' => isset($this->last_month_count) ? $this->last_month_count : 0,
            'last_month_revenue' => isset($this->last_month_revenue) ? $this->last_month_revenue / 100 : 0,
            'last_month_gross_profit' => isset($this->last_month_gross_profit) ? $this->last_month_gross_profit / 100 : 0,
            'last_month_gross_profit_margin' => isset($this->last_month_gross_profit_margin) ? $this->last_month_gross_profit_margin : 0,
            'last_two_month_count' => isset($this->last_two_month_count) ? $this->last_two_month_count : 0,
            'last_two_month_revenue' => isset($this->last_two_month_revenue) ? $this->last_two_month_revenue / 100 : 0,
            'last_two_month_gross_profit' => isset($this->last_two_month_gross_profit) ? $this->last_two_month_gross_profit / 100 : 0,
            'last_two_month_gross_profit_margin' => isset($this->last_two_month_gross_profit_margin) ? $this->last_two_month_gross_profit_margin : 0,
        ];
    }

    public function only(...$attributes)
    {
        return collect($this->resolve())
            ->only($attributes)
            ->toArray();
    }

    protected function getNearestTime($time1, $time2)
    {
        $now = Carbon::now();

        $diff1 = $time1 ? $now->diffInSeconds(Carbon::parse($time1)) : PHP_INT_MAX;
        $diff2 = $time2 ? $now->diffInSeconds(Carbon::parse($time2)) : PHP_INT_MAX;

        return $diff1 <= $diff2 ? Carbon::parse($time1) : Carbon::parse($time2);
    }
}
