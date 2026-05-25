<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\GetUserTimezone;

class CustomerResource extends JsonResource
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
            'id' => $this->id,
            'ref_id' => $this->id + 20000,
            'begin_date' => Carbon::parse($this->begin_date)->toDateString(),
            'begin_date_short' => isset($this->begin_date) ? Carbon::parse($this->begin_date)->setTimezone($this->getUserTimezone())->format('ymd') : null,
            'category_id' => CategoryResource::make($this->whenLoaded('category')),
            'code' => $this->code,
            'created_at' => Carbon::parse($this->created_at)->toDateString(),
            'customerVendBindings' => CustomerVendBindingResource::collection($this->whenLoaded('customerVendBindings')),
            'person_json' => $this->person_json,
            'name' => $this->name,
            'first_transaction_id' => $this->first_transaction_id,
            'frequency_per_week_status' => $this->frequency_per_week_status,
            'frequency_per_week_status_name' => $this->frequency_per_week_status ? Customer::FREQUENCY_PER_WEEK_STATUSES_MAPPING[$this->frequency_per_week_status] : null,
            'is_active' => $this->is_active,
            'latitude' => $this->latitude,
            'lastOpsJobItem' => OpsJobItemResource::make($this->whenLoaded('lastOpsJobItem')),
            'lastSecondOpsJobItem' => OpsJobItemResource::make($this->whenLoaded('lastSecondOpsJobItem')),
            'location_type_id' => $this->location_type_id,
            'longitude' => $this->longitude,
            'nextInvoiceDriver' => UserResource::make($this->whenLoaded('nextInvoiceDriver')),
            'nextOpsJobItem' => OpsJobItemResource::make($this->whenLoaded('nextOpsJobItem')),
            'operator_id' => $this->operator_id,
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'operator_code' => $this->operator_code,
            'operator_name' => $this->operator_name,
            'ops_note' => $this->ops_note,
            'person_id' => $this->person_id,
            'photos' => AttachmentResource::collection($this->whenLoaded('photos')),
            'preferred_visit_days_json' => $this->preferred_visit_days_json,
            'profile_id' => $this->profile_id,
            'selling_price_type' => $this->selling_price_type,
            'status_id' => $this->status_id,
            'thirty_days_over_full_load_ratio' => isset($this->thirty_days_over_full_load_ratio) ? $this->thirty_days_over_full_load_ratio : 0,
            'total_full_load_amount' => isset($this->total_full_load_amount) ? $this->total_full_load_amount/100 : 0,
            'updated_at' => Carbon::parse($this->updated_at)->toDateString(),
            'virtual_customer_code' => $this->virtual_customer_code,
            'virtual_customer_prefix' => $this->virtual_customer_prefix,
            'zone_id' => $this->zone_id,
            'zone_name' => isset($this->zone) ? $this->zone->name : null,
            'zone' => ZoneResource::make($this->whenLoaded('zone')),
            'accountManager' => UserResource::make($this->whenLoaded('handledBy')),
            'addresses' => AddressResource::make($this->whenLoaded('addresses')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'billingAddress' => AddressResource::make($this->whenLoaded('billingAddress')),
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'deliveryAddress' => AddressResource::make($this->whenLoaded('deliveryAddress')),
            'firstTransaction' => TransactionResource::make($this->whenLoaded('firstTransaction')),
            'status' => StatusResource::make($this->whenLoaded('status')),
            'tags' => TagResource::collection($this->whenLoaded('tagBindings')),
            'vend' => VendResource::make($this->whenLoaded('vend')),
            'vends' => VendResource::collection($this->whenLoaded('vends')),
            'vendTransactionTotalsJson' => $this->totals_json,
            'contract_commission_type' => $this->contract_commission_type,
            'contract_commission_value' => $this->contract_commission_value,
            'contract_commission_value2' => $this->contract_commission_value2,
            'contract_ps_term' => $this->contract_ps_term,
            'is_external_subsidize' => $this->is_external_subsidize ?? false,
            'external_subsidize_amount' => $this->external_subsidize_amount,
            'contract_from' => $this->contract_from ? $this->contract_from->toDateString() : null,
            'contract_until' => $this->contract_until ? $this->contract_until->toDateString() : null,
            'contract_auto_renewal' => $this->contract_auto_renewal ?? false,
            'contract_notice_period' => $this->contract_notice_period,
            'contract_remarks' => $this->contract_remarks,
            'contract_detail_updated_at' => $this->contract_detail_updated_at ? Carbon::parse($this->contract_detail_updated_at)->setTimezone($this->getUserTimezone())->format('Y-m-d h:i a') : null,
            'contract_detail_updated_by' => $this->contract_detail_updated_by,
            'contract_detail_updated_by_name' => $this->contractDetailUpdatedBy ? $this->contractDetailUpdatedBy->name : null,
        ];
    }
}
