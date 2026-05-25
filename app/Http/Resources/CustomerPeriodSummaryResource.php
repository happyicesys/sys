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
                    // Begin Date — rendered in the Customer column on the
                    // Summary page (right under the Ref Price chip).
                    // Mirrors Customer/Edit.vue's form.begin_date.
                    'begin_date' => optional($c->begin_date)->toDateString(),
                    // Latest contract attachment (if any) — drives the
                    // "Contract Attachment" hyperlink in the Customer column
                    // on the Summary page. The contracts() relation already
                    // orders DESC by created_at, so .first() = most recent.
                    'latest_contract' => $c->relationLoaded('contracts') && $c->contracts->isNotEmpty()
                        ? [
                            'id' => $c->contracts->first()->id,
                            'name' => $c->contracts->first()->name,
                            'full_url' => $c->contracts->first()->full_url,
                        ]
                        : null,
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
                    // External Subsidize — pulled live from the customer's
                    // current contract (Customer/Edit.vue). Drives the
                    // "External Subsidize" + "Net Loc Fee" lines stacked under
                    // the Location Fees column. external_subsidize_amount is in
                    // dollars; the Vue side converts to cents for display/math.
                    'is_external_subsidize' => (bool) $c->is_external_subsidize,
                    'external_subsidize_amount' => $c->external_subsidize_amount !== null ? (float) $c->external_subsidize_amount : null,
                    'operator' => $c->relationLoaded('operator') && $c->operator ? [
                        'id' => $c->operator->id,
                        'code' => $c->operator->code,
                        'name' => $c->operator->name,
                        // GST/VAT rate (decimal percent, e.g. 9.00 for 9%).
                        // Used by Customer/Summary.vue's Sales column to
                        // render the excl-GST sub-line under the displayed
                        // incl-GST sales figure.
                        'gst_vat_rate' => $c->operator->gst_vat_rate !== null
                            ? (float) $c->operator->gst_vat_rate : 0.0,
                    ] : null,
                    'delivery_address' => $c->relationLoaded('deliveryAddress') && $c->deliveryAddress
                        ? AddressResource::make($c->deliveryAddress)->resolve()
                        : null,
                    'location_type' => $c->relationLoaded('locationType') && $c->locationType ? [
                        'id' => $c->locationType->id,
                        'name' => $c->locationType->name,
                    ] : null,
                    // Location Grading — three char(1) columns (A/B/C/null)
                    // captured on Customer/Edit.vue. Surfaced as a flat object
                    // so the Summary page's Location Grading column can show
                    // all three picks per row at a glance. See
                    // Customer::LOCATION_GRADING_CATEGORIES for the rubric.
                    'location_grading' => [
                        'placement' => $c->location_grading_placement,
                        'access' => $c->location_grading_access,
                        'flexibility' => $c->location_grading_flexibility,
                    ],
                    // Contract end / renewal / notice period — captured on
                    // Customer/Edit.vue and surfaced as their own column on
                    // the Summary page. contract_until is cast to a Carbon
                    // date in the Customer model, so optional()->toDateString()
                    // keeps the wire payload as plain YYYY-MM-DD.
                    'contract_until' => optional($c->contract_until)->toDateString(),
                    'contract_auto_renewal' => (bool) $c->contract_auto_renewal,
                    // Stored as one of the labels in Customer::NOTICE_PERIOD_OPTIONS
                    // ('1 wk', '1.5 mth', 'NO need', 'Cant ETerm', etc.). String,
                    // not numeric — see migration 2026_05_13_000000.
                    'contract_notice_period' => $c->contract_notice_period,
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
                    // Deduplicated list of delivery platform names (e.g. "Grab")
                    // across all of the customer's bound vends. Drives the small
                    // green platform badge rendered next to the customer name on
                    // the Customer Summary page. Empty array when no vend has an
                    // active delivery-platform mapping.
                    'delivery_platforms' => $c->relationLoaded('vends')
                        ? $c->vends
                            ->flatMap(function ($v) {
                                if (!$v->relationLoaded('deliveryProductMappingVends')) {
                                    return [];
                                }
                                return $v->deliveryProductMappingVends->map(function ($dpmv) {
                                    $platform = optional(optional($dpmv->deliveryProductMapping)
                                        ->deliveryPlatformOperator)->deliveryPlatform;
                                    return $platform ? $platform->name : null;
                                });
                            })
                            ->filter()
                            ->unique()
                            ->values()
                            ->all()
                        : [],
                    // Customer-level Notes (parked on the customer record so
                    // it carries across any period filter on this page).
                    // notes_updated_by_user is the user object resolved via
                    // the notesUpdatedBy() relation; null when no one has
                    // touched the field yet. See migration
                    // 2026_05_14_090000_add_notes_to_customers.
                    'notes' => $c->notes,
                    'notes_updated_at' => optional($c->notes_updated_at)->toDateTimeString(),
                    'notes_updated_by_user' => $c->relationLoaded('notesUpdatedBy') && $c->notesUpdatedBy ? [
                        'id' => $c->notesUpdatedBy->id,
                        'name' => $c->notesUpdatedBy->name,
                    ] : null,
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
