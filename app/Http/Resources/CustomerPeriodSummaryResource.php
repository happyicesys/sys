<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a CustomerPeriodSummary row for the Customer Management > Summary page.
 *
 * Using a Resource (rather than a raw paginator) gives us the
 * { data, links, meta } shape that the Vue page expects, mirroring
 * CustomerResource on the Customer Index page.
 */
class CustomerPeriodSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'operator_id' => $this->operator_id,
            'year_month' => optional($this->year_month)->toDateString(),
            'period_start' => optional($this->period_start)->toDateString(),
            'period_end' => optional($this->period_end)->toDateString(),
            'is_current_month' => (bool) $this->is_current_month,
            'as_of_date' => optional($this->as_of_date)->toDateString(),
            'sales_cents' => (int) $this->sales_cents,
            'gross_earning_cents' => (int) $this->gross_earning_cents,
            'location_fees_cents' => (int) $this->location_fees_cents,
            'location_earning_cents' => (int) $this->location_earning_cents,
            'location_earning_rate' => (float) $this->location_earning_rate,
            'transaction_count' => (int) $this->transaction_count,
            'vend_count' => (int) $this->vend_count,
            // Lifetime-to-date sum of gross_earning_cents for this customer up
            // to and including the latest month visible on the current view.
            // Attached by CustomerController::attachAccumulatedGrossEarning().
            'accumulate_gross_earning_cents' => isset($this->accumulate_gross_earning_cents)
                ? (int) $this->accumulate_gross_earning_cents
                : null,
            'contract_commission_type' => $this->contract_commission_type,
            'contract_commission_value' => $this->contract_commission_value !== null ? (float) $this->contract_commission_value : null,
            'contract_commission_value2' => $this->contract_commission_value2 !== null ? (float) $this->contract_commission_value2 : null,
            'contract_ps_term' => $this->contract_ps_term !== null ? (float) $this->contract_ps_term : null,
            'customer' => $this->whenLoaded('customer', function () {
                $c = $this->customer;
                return [
                    'id' => $c->id,
                    'ref_id' => $c->id ? $c->id + \App\Models\Customer::RUNNING_NUMBER_INIT : null,
                    'name' => $c->name,
                    'code' => $c->code,
                    'virtual_customer_code' => $c->virtual_customer_code,
                    'virtual_customer_prefix' => $c->virtual_customer_prefix,
                    'person_id' => $c->person_id ?? null,
                    'is_active' => (bool) $c->is_active,
                    'selling_price_type' => $c->selling_price_type,
                    'contract_commission_type' => $c->contract_commission_type,
                    'operator' => $c->relationLoaded('operator') && $c->operator ? [
                        'id' => $c->operator->id,
                        'code' => $c->operator->code,
                        'name' => $c->operator->name,
                    ] : null,
                    'delivery_address' => $c->relationLoaded('deliveryAddress') && $c->deliveryAddress
                        ? AddressResource::make($c->deliveryAddress)->resolve()
                        : null,
                    'location_type' => $c->relationLoaded('locationType') && $c->locationType ? [
                        'id' => $c->locationType->id,
                        'name' => $c->locationType->name,
                    ] : null,
                    'vend' => $c->relationLoaded('vend') && $c->vend ? [
                        'id' => $c->vend->id,
                        'code' => $c->vend->code,
                        'prefix' => $c->vend->relationLoaded('vendPrefix') && $c->vend->vendPrefix
                            ? $c->vend->vendPrefix->name
                            : null,
                    ] : null,
                    // Full list of vends bound to this customer, sorted by
                    // code ascending. Powers the line-broken display in the
                    // Vend ID column on the Summary page.
                    'vends' => $c->relationLoaded('vends')
                        ? $c->vends
                            ->sortBy(fn ($v) => (string) $v->code, SORT_NATURAL | SORT_FLAG_CASE)
                            ->values()
                            ->map(function ($v) {
                                return [
                                    'id'     => $v->id,
                                    'code'   => $v->code,
                                    'prefix' => $v->relationLoaded('vendPrefix') && $v->vendPrefix
                                        ? $v->vendPrefix->name
                                        : null,
                                ];
                            })
                            ->all()
                        : [],
                    'tag_bindings' => $c->relationLoaded('tagBindings')
                        ? $c->tagBindings->map(function ($tb) {
                            return [
                                'id' => $tb->id,
                                'tag' => $tb->relationLoaded('tag') && $tb->tag ? [
                                    'id' => $tb->tag->id,
                                    'name' => $tb->tag->name,
                                ] : null,
                            ];
                        })
                        : [],
                ];
            }),
        ];
    }
}
