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
            'cardTerminal' => CardTerminalResource::make($this->whenLoaded('cardTerminal')),
            'card_terminal_id' => isset($this->card_terminal_id) ? $this->card_terminal_id : null,
            // Card terminal name (Nayax / Nets / Nets-Auresys / PAX / MLS).
            // Comes from the user-defined card_terminals table — replaces the
            // unreliable acb_vmc_pa_json->CSHL_MFG read.
            'card_terminal_name' => $this->whenLoaded('cardTerminal', function () {
                return $this->cardTerminal?->name;
            }, isset($this->card_terminal_name) ? $this->card_terminal_name : null),
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
            'last_thirty_days_jobs_done_count' => isset($this->last_thirty_days_jobs_done_count) ? (int) $this->last_thirty_days_jobs_done_count : 0,
            'thirty_days_stock_in_delta_amount' => isset($this->thirty_days_stock_in_delta_amount) ? $this->thirty_days_stock_in_delta_amount : 0,
            'thirty_days_stock_in_delta_percent' => isset($this->thirty_days_stock_in_delta_percent) ? $this->thirty_days_stock_in_delta_percent : 0,
            'last_updated_at' => isset($this->last_updated_at) ? Carbon::parse($this->last_updated_at)->setTimezone($this->getUserTimezone())->shortRelativeDiffForHumans() : null,
            'lcd_monitor' => isset($this->lcd_monitor_id) ? Vend::LCD_MONITOR_MAPPINGS[$this->lcd_monitor_id] : null,
            // Short label variant used by the Customer Index "Payment Device"
            // column so the badge stays narrow. Falls back to null when the
            // mapping doesn't contain the id (defensive — keeps the badge
            // from rendering a "[]" if a stale id slips in).
            'lcd_monitor_short' => isset($this->lcd_monitor_id) && isset(Vend::LCD_MONITOR_SHORT_MAPPINGS[$this->lcd_monitor_id])
                ? Vend::LCD_MONITOR_SHORT_MAPPINGS[$this->lcd_monitor_id]
                : null,
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
            // Site grouping — cluster id for the "stuck together" row border on
            // the Operation Dashboard (null = ungrouped).
            'customer_group_id' => isset($this->customer_group_id) ? $this->customer_group_id : null,
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
            // Contract end date (Customer Edit page "Contract Until" field).
            // Distinct from termination_date — contract_until is the contract's
            // expiry, termination_date is when the customer relationship ended.
            'contract_until' => isset($this->contract_until) ? Carbon::parse($this->contract_until)->setTimezone($this->getUserTimezone())->format('Y-m-d') : null,
            'contract_until_short' => isset($this->contract_until) ? Carbon::parse($this->contract_until)->setTimezone($this->getUserTimezone())->format('ymd') : null,
            // Customer's contract auto-renewal flag (boolean). Drives the
            // green-tick / red-cross indicator next to Contract End Date on
            // the Customer Index page.
            'contract_auto_renewal' => isset($this->contract_auto_renewal) ? (bool) $this->contract_auto_renewal : null,
            // Contract fields (snapshot of the customer's contract terms; drives
            // Contract Type / Location Fees / VendingEarning columns on the
            // Customer Index page).
            'contract_commission_type' => $this->contract_commission_type ?? null,
            'contract_commission_value' => $this->contract_commission_value ?? null,
            'contract_commission_value2' => $this->contract_commission_value2 ?? null,
            'contract_ps_term' => $this->contract_ps_term ?? null,
            // External Subsidize — pulled live from the customer's current
            // contract (Customer/Edit.vue). Drives the "Ext Subsidize" + "Net
            // Loc Fee" lines in the Contract Type column on Vend/CustomerIndex.
            // external_subsidize_amount is in dollars; the Vue side converts to
            // cents for display/math. Null on /vends rows where it isn't selected.
            'is_external_subsidize' => isset($this->is_external_subsidize) ? (bool) $this->is_external_subsidize : false,
            'external_subsidize_amount' => isset($this->external_subsidize_amount) && $this->external_subsidize_amount !== null ? (float) $this->external_subsidize_amount : null,
            // Notice Period — free-text since 2026_05_13 (string column).
            // Powers the Notice Period line under Contract End Date on
            // Vend/CustomerIndex; null on /vends rows where it isn't selected.
            'contract_notice_period' => $this->contract_notice_period ?? null,
            // Per-vend earnings (computed in VendController@indexCustomer).
            'location_fees_cents' => isset($this->location_fees_cents) ? (int) $this->location_fees_cents : null,
            'thirty_days_vending_earning_cents' => isset($this->thirty_days_vending_earning_cents) ? (int) $this->thirty_days_vending_earning_cents : null,
            'accumulate_vending_earning_cents' => isset($this->accumulate_vending_earning_cents) ? (int) $this->accumulate_vending_earning_cents : null,
            // Location Grading — three independent A/B/C codes per customer.
            'location_grading_placement' => $this->location_grading_placement ?? null,
            'location_grading_access' => $this->location_grading_access ?? null,
            'location_grading_flexibility' => $this->location_grading_flexibility ?? null,
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
            // Audit pair for the inline Ops Note edit on Vend/CustomerIndex.
            // Populated only on the customers-index path (where the SELECT and
            // eager-load include them); falls back to null on the regular
            // /vends path so the field is safe to read either way.
            'ops_note_updated_at' => isset($this->ops_note_updated_at)
                ? Carbon::parse($this->ops_note_updated_at)->setTimezone($this->getUserTimezone())->toDateTimeString()
                : null,
            'ops_note_updated_by_user' => $this->whenLoaded('opsNoteUpdatedBy', function () {
                return $this->opsNoteUpdatedBy ? [
                    'id' => $this->opsNoteUpdatedBy->id,
                    'name' => $this->opsNoteUpdatedBy->name,
                ] : null;
            }),
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
            // Active campaign name(s) bound to this machine through its APK
            // settings (Vend -> apkSettings -> campaigns). Drives the
            // "Campaign" badge under the Ref Price line on Vend/CustomerIndex.
            // "Active" = is_active flag set AND not yet expired (end_at has not
            // passed; a null end_at never expires). The badge also shows the
            // expiry date (end_at). Empty array when none/unloaded.
            'campaigns' => $this->whenLoaded('apkSettings', function () {
                $now = Carbon::now();

                return $this->apkSettings
                    ->flatMap(function ($apkSetting) {
                        return $apkSetting->relationLoaded('campaigns')
                            ? $apkSetting->campaigns
                            : collect();
                    })
                    ->filter(function ($campaign) use ($now) {
                        if (! $campaign->is_active) {
                            return false;
                        }

                        // Not expired: no end date, or end_at (end of day) is
                        // still in the future.
                        return $campaign->end_at === null
                            || Carbon::parse($campaign->end_at)->endOfDay()->gte($now);
                    })
                    ->unique('id')
                    ->map(fn ($campaign) => [
                        'id' => $campaign->id,
                        'name' => $campaign->name,
                        'end_at' => $campaign->end_at
                            ? Carbon::parse($campaign->end_at)->setTimezone($this->getUserTimezone())->format('Y-m-d')
                            : null,
                    ])
                    ->values();
            }, []),
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
            'vendTwoDaysErrorTransactions' => VendTransactionResource::collection($this->whenLoaded('vendTwoDaysErrorTransactions')),
            'vendTransactionTotalsJson' => isset($this->vend_transaction_totals_json) ? $this->vend_transaction_totals_json : null,
            // PWRON 1d / 2d / 3d counts sourced from vend_daily_stats — drives
            // the trend block at the bottom of the Error column on
            // Vend/CustomerIndex (populated in VendController@indexCustomer).
            // Default to null on paths where the enrichment hasn't run so the
            // view can hide the block instead of showing zero-everywhere noise.
            'pwron_1d_count' => isset($this->pwron_1d_count) ? (int) $this->pwron_1d_count : null,
            'pwron_2d_count' => isset($this->pwron_2d_count) ? (int) $this->pwron_2d_count : null,
            'pwron_3d_count' => isset($this->pwron_3d_count) ? (int) $this->pwron_3d_count : null,
            // "# of No Found in Txn" 1d / 2d / 3d counts — same vend_daily_stats
            // table as PWRON, different metric ('nofound_txn'). Written by
            // LogNofoundTxnIfStillMissing (5-min delayed) and undone by
            // VendTransactionService -> DecrementVendDailyStat when the
            // matching vend_transactions row finally lands. Null-default so
            // routes that don't enrich (e.g. /vends) hide the block.
            'nofound_txn_1d_count' => isset($this->nofound_txn_1d_count) ? (int) $this->nofound_txn_1d_count : null,
            'nofound_txn_2d_count' => isset($this->nofound_txn_2d_count) ? (int) $this->nofound_txn_2d_count : null,
            'nofound_txn_3d_count' => isset($this->nofound_txn_3d_count) ? (int) $this->nofound_txn_3d_count : null,
            'vendSevenDaysErrorTransactions' => VendTransactionResource::collection($this->whenLoaded('vendSevenDaysErrorTransactions')),
            'vend_id' => isset($this->vend_id) ? $this->vend_id : null,
            'virtual_customer_code' => isset($this->virtual_customer_code) ? $this->virtual_customer_code : null,
            'virtual_customer_prefix' => isset($this->virtual_customer_prefix) ? $this->virtual_customer_prefix : null,
            'virtual_vend_records_thirty_days_amount_average' => isset($this->virtual_vend_records_thirty_days_amount_average) ? $this->virtual_vend_records_thirty_days_amount_average / 100 : 0,
            'zone_name' => isset($this->zone_name) ? $this->zone_name : null,
            'zone_id' => isset($this->zone_id) ? $this->zone_id : null,
            // Customer Tag / Note column on Vend/CustomerIndex.vue.
            // VendResource is fed Customer rows in the customers-index path
            // (VendController@indexCustomer eager-loads tagBindings + tag and
            // selects customers.notes / notes_updated_at / notes_updated_by),
            // so we expose them here for the new combined column. Vend rows
            // (where these columns don't exist) fall back to defaults.
            'notes' => isset($this->notes) ? $this->notes : null,
            'notes_updated_at' => isset($this->notes_updated_at)
                ? Carbon::parse($this->notes_updated_at)->setTimezone($this->getUserTimezone())->toDateTimeString()
                : null,
            'notes_updated_by_user' => $this->whenLoaded('notesUpdatedBy', function () {
                return $this->notesUpdatedBy ? [
                    'id' => $this->notesUpdatedBy->id,
                    'name' => $this->notesUpdatedBy->name,
                ] : null;
            }),
            'tag_bindings' => $this->whenLoaded('tagBindings', function () {
                return $this->tagBindings->map(function ($tb) {
                    return [
                        'id' => $tb->id,
                        'tag' => $tb->relationLoaded('tag') && $tb->tag ? [
                            'id' => $tb->tag->id,
                            'name' => $tb->tag->name,
                        ] : null,
                    ];
                });
            }, []),
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
