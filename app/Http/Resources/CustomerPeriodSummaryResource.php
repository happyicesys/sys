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
        // Action-triggered lock rule: a row stays LIVE until someone explicitly
        // locks it. While unlocked (locked_at IS NULL — this includes the
        // current in-progress month, which has no Lock button), every element
        // of the Placement Contract Detail (Contract Type, the commission value
        // / Location Fees Rate, Location Fees, External Subsidize) and the
        // derived Vend Earning are re-derived LIVE from the customer's current
        // contract, so a contract edit shows up immediately. Once locked, the
        // row is frozen to the per-period snapshot stored at lock time.
        $isCurrentMonth = (bool) $this->is_current_month;
        $isLocked = $this->locked_at !== null;

        // Defaults = the frozen per-period snapshot (used as-is for completed
        // months, and as the fallback when the customer relation isn't loaded).
        $contractType = $this->contract_commission_type;
        $contractValue = $this->contract_commission_value !== null ? (float) $this->contract_commission_value : null;
        $contractValue2 = $this->contract_commission_value2 !== null ? (float) $this->contract_commission_value2 : null;
        $contractPsTerm = $this->contract_ps_term !== null ? (float) $this->contract_ps_term : null;
        $locationFeesCents = (int) $this->location_fees_cents;
        $externalSubsidizeCents = (int) $this->external_subsidize_cents;
        $locationEarningCents = (int) $this->location_earning_cents;
        $locationEarningRate = (float) $this->location_earning_rate;

        if (!$isLocked && $this->relationLoaded('customer') && $this->customer) {
            $c = $this->customer;

            // Live contract terms straight off the customer record.
            $contractType = $c->contract_commission_type;
            $contractValue = $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null;
            $contractValue2 = $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null;
            $contractPsTerm = $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null;

            $grossCents = (int) $this->gross_earning_cents;
            $salesCents = (int) $this->sales_cents;
            $gstRatePct = ($c->relationLoaded('operator') && $c->operator && $c->operator->gst_vat_rate !== null)
                ? (float) $c->operator->gst_vat_rate
                : 0.0;

            // Recompute Location Fees with the SAME formula the aggregator uses,
            // but against the live contract terms. sales/gross stay the
            // aggregated (daily) figures — only the contract inputs are live.
            $locationFeesCents = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                $contractType,
                $contractValue,
                $contractValue2,
                $contractPsTerm,
                $salesCents,
                $grossCents,
                $gstRatePct
            );

            $externalSubsidizeCents = ($c->is_external_subsidize && $c->external_subsidize_amount !== null)
                ? (int) round(((float) $c->external_subsidize_amount) * 100)
                : 0;

            // Vend Earning = Gross − (Location Fees − External Subsidize).
            $locationEarningCents = $grossCents - ($locationFeesCents - $externalSubsidizeCents);
            $locationEarningRate = $salesCents > 0
                ? round($locationEarningCents / $salesCents, 4)
                : 0.0;
        }

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'operator_id' => $this->operator_id,
            'year_month' => optional($this->year_month)->toDateString(),
            'period_start' => optional($this->period_start)->toDateString(),
            'period_end' => optional($this->period_end)->toDateString(),
            'is_current_month' => (bool) $this->is_current_month,
            'as_of_date' => optional($this->as_of_date)->toDateString(),
            // Action-triggered lock state. is_locked drives the Lock column on
            // Customer/Summary.vue; locked_by_user powers the "Locked by X" tip.
            'is_locked' => $isLocked,
            'locked_at' => optional($this->locked_at)->toDateTimeString(),
            'locked_by_user' => $this->relationLoaded('lockedBy') && $this->lockedBy
                ? ['id' => $this->lockedBy->id, 'name' => $this->lockedBy->name]
                : null,
            // "Email Performance Report" audit (from the Report Content modal
            // button on a LOCKED row). The modal renders "Last sent by X at Y"
            // when these are set; never sent stays null.
            'report_emailed_at' => optional($this->report_emailed_at)->toDateTimeString(),
            'report_emailed_by_user' => $this->relationLoaded('reportEmailedBy') && $this->reportEmailedBy
                ? ['id' => $this->reportEmailedBy->id, 'name' => $this->reportEmailedBy->name]
                : null,
            // Paid state. is_paid drives the Paid/Unpaid button visibility on
            // Customer/Summary.vue; paid_by_user powers the "Paid by X" tip.
            // Only meaningful when is_locked = true (UI enforces the order).
            'is_paid' => $this->paid_at !== null,
            'paid_at' => optional($this->paid_at)->toDateTimeString(),
            'paid_by_user' => $this->relationLoaded('paidBy') && $this->paidBy
                ? ['id' => $this->paidBy->id, 'name' => $this->paidBy->name]
                : null,
            // Reverse-action audit — surfaces "last unpaid by X at Y" and
            // "last unlocked by X at Y" in tooltips, so the user can see the
            // most recent reversal even after the row goes back into a
            // Locked / Paid state on the next cycle.
            'last_unpaid_at' => optional($this->last_unpaid_at)->toDateTimeString(),
            'last_unpaid_by_user' => $this->relationLoaded('lastUnpaidBy') && $this->lastUnpaidBy
                ? ['id' => $this->lastUnpaidBy->id, 'name' => $this->lastUnpaidBy->name]
                : null,
            'last_unlocked_at' => optional($this->last_unlocked_at)->toDateTimeString(),
            'last_unlocked_by_user' => $this->relationLoaded('lastUnlockedBy') && $this->lastUnlockedBy
                ? ['id' => $this->lastUnlockedBy->id, 'name' => $this->lastUnlockedBy->name]
                : null,
            'sales_cents' => (int) $this->sales_cents,
            'gross_earning_cents' => (int) $this->gross_earning_cents,
            // Completed months: frozen snapshot. Current month: re-derived live
            // from the customer's contract (see the lock rule above).
            'location_fees_cents' => $locationFeesCents,
            // NET of External Subsidize. For completed months this is the
            // frozen stored value; for the current month it's re-derived live
            // (see the lock rule at the top of this method).
            'location_earning_cents' => $locationEarningCents,
            'location_earning_rate' => $locationEarningRate,
            // External Subsidize (cents). Completed months use the frozen
            // per-period snapshot; the current month uses the live value.
            // Net Loc Fee column = location_fees_cents − external_subsidize_cents.
            'external_subsidize_cents' => $externalSubsidizeCents,
            'transaction_count' => (int) $this->transaction_count,
            'vend_count' => (int) $this->vend_count,
            // "# of Job" — ops job items that serviced this customer in the
            // period (pre-aggregated; see CustomerSummaryAggregator).
            'job_count' => (int) $this->job_count,
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
            // Resolved PREVIOUS-month figures for the current (in-progress)
            // month — attached by CustomerController::attachPreviousMonthSummary()
            // and only present on current-month rows. Lets the Vue side draw
            // month-over-month trend arrows in the single-month "Current" view,
            // where last month isn't itself a visible row. Same cent fields the
            // trend getters read (sales / gross / location fees / vend earning /
            // external subsidize). Null when there's no prior month on record.
            'prev_month' => isset($this->previous_month_summary)
                ? $this->previous_month_summary
                : null,
            // Which contract terms changed versus the immediately-preceding
            // period — drives the tiny "New" badge on the Summary page. Keys:
            // placement_type / contract_until / auto_renewal / notice_period.
            // Attached by CustomerController::attachContractChangeFlags().
            'contract_diff' => isset($this->contract_diff)
                ? $this->contract_diff
                : null,
            // Placement Contract Detail — completed months show the frozen
            // snapshot; the current month shows live contract terms.
            'contract_commission_type' => $contractType,
            'contract_commission_value' => $contractValue,
            'contract_commission_value2' => $contractValue2,
            'contract_ps_term' => $contractPsTerm,
            'customer' => $this->whenLoaded('customer', function () {
                $c = $this->customer;
                return [
                    'id' => $c->id,
                    'ref_id' => $c->id ? $c->id + \App\Models\Customer::RUNNING_NUMBER_INIT : null,
                    'name' => $c->name,
                    'code' => $c->code,
                    // CMS "Company" field (com_remark). Rendered as the
                    // "Company / Name" sub-line in the Address column on the
                    // Summary page. See migration 2026_05_27_000000.
                    'company_remark' => $c->company_remark,
                    // Primary contact (morphOne). Only the name is surfaced —
                    // used for the "Contact Person" sub-line stacked under the
                    // Address column on the Summary page.
                    'contact' => $c->relationLoaded('contact') && $c->contact
                        ? ['name' => $c->contact->name]
                        : null,
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
