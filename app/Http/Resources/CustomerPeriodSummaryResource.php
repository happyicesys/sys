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
            // Lifetime-to-date sum of location_earning_cents (= gross_earning
            // - location_fees, a.k.a. Vending Earning) for this customer up to
            // and including the latest month visible on the current view.
            // Attached by CustomerController::attachAccumulatedVendingEarning().
            'accumulate_vending_earning_cents' => isset($this->accumulate_vending_earning_cents)
                ? (int) $this->accumulate_vending_earning_cents
                : null,
            // Latest API Invoice (if any) for this row's (customer, period)
            // — populated by CustomerController::attachExistingInvoice().
            // Null when no successful CMS transaction exists yet. The Vue
            // page uses this to render the "API Rpt" badge and to decide
            // whether the per-row Create button should ask-to-confirm.
            'existing_invoice' => isset($this->existing_invoice)
                ? $this->existing_invoice
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
                    // Performance Report email opt-in (drives the Email button
                    // in the Action column on Customer/Summary.vue).
                    'report_email' => $c->report_email,
                    'is_report_email_enabled' => (bool) $c->is_report_email_enabled,
                    // Whether the Report Content modal would render anything
                    // for this customer's contract. False for F/S (per spec)
                    // and any contract type with missing required values.
                    // Computed via PerformanceReportContentService so the
                    // Vue button stays in sync with the actual API response.
                    'has_report_content' => (new \App\Services\PerformanceReportContentService())->isAvailable($c),
                    // Contract values needed for the contract_commission_value(2)
                    // contract_ps_term — the Vue side already has these for
                    // some columns but the modal/action layer needs them too.
                    'contract_commission_value' => $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null,
                    'contract_commission_value2' => $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null,
                    'contract_ps_term' => $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null,
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
