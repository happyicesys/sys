<?php

namespace App\Http\Resources;

use App\Models\PaymentMethod;
use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class VendTransactionDBResource extends JsonResource
{
    use GetUserTimezone;

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'amount' => number_format($this->amount/ 100, 2, '.', ','),
            'customerJson' => isset($this->customer_json) ? json_decode($this->customer_json) : null,
            'customer_code' => isset($this->customer_code) ? $this->customer_code : null,
            'customer_name' => isset($this->customer_name) ? $this->customer_name : null,
            'full_name' => isset($this->customer_code) ? $this->customer_code . ' - ' . $this->customer_name : $this->vend_name,
            'gross_profit' => number_format($this->gross_profit/ 100, 2, '.', ','),
            'is_payment_received' => isset($this->is_payment_received) ? $this->is_payment_received : null,
            'locationTypeJson' => isset($this->location_type_json) ? json_decode($this->location_type_json) : null,
            'order_id' => isset($this->order_id) ? $this->order_id : null,
            'operatorJson' => isset($this->operator_json) ? json_decode($this->operator_json) : null,
            'payment_method_name' => isset($this->payment_method_name) ? $this->payment_method_name : null,
            'product_code' => isset($this->product_code) ? $this->product_code : null,
            'product_name' => isset($this->product_name) ? $this->product_name : null,
            'productJson' => isset($this->product_json) ? json_decode($this->product_json) : null,
            'revenue' => number_format($this->revenue/ 100, 2, '.', ','),
            'transaction_datetime' => isset($this->transaction_datetime) ? Carbon::parse($this->transaction_datetime)->setTimezone($this->getUserTimezone())->format('ymd h:i a') : null,
            'unit_cost' => number_format($this->unit_cost/ 100, 2, '.', ','),
            'gross_profit_margin' => number_format($this->gross_profit/ $this->revenue * 100, 2, '.', ','),
            'vend_code' => isset($this->vend_code) ? $this->vend_code : null,
            'vend_channel_code' => isset($this->vend_channel_code) ? $this->vend_channel_code : null,
            'vend_channel_error_code' => isset($this->vend_channel_error_code) ? $this->vend_channel_error_code : null,
            'vend_channel_error_desc' => isset($this->vend_channel_error_desc) ? $this->vend_channel_error_desc : null,
            'vendJson' => isset($this->vend_json) ? json_decode($this->vend_json) : null ,
            'vendTransactionJson' => isset($this->vend_transaction_json) ? json_decode($this->vend_transaction_json) : null,
        ];
    }
}
