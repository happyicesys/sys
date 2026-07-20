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

        // This row's calendar month — used for the removed-date lock exception
        // and the flat-fee proration ratio below.
        $monthStart = $this->year_month
            ? \Carbon\Carbon::parse($this->year_month)->startOfMonth()
            : null;

        // Removed-aware lock exception. When a site is Removed (status), its
        // Removed Date is captured on the customer. If that date falls within
        // THIS row's month, the current in-progress month can be locked early —
        // even if the Removed Date is a few days in the FUTURE — because
        // management has decided the removal and the prorated payment may need
        // to clear in advance. Drives the Lock-button exception on Summary.vue
        // and the backend guard in CustomerController.
        $removedDate = ($this->relationLoaded('customer') && $this->customer)
            ? $this->customer->removed_date
            : null;
        $isRemovedInPeriod = false;
        if ($removedDate && $monthStart) {
            $rd = $removedDate instanceof \Carbon\Carbon
                ? $removedDate->copy()
                : \Carbon\Carbon::parse($removedDate);
            $monthEnd = $monthStart->copy()->endOfMonth();
            $isRemovedInPeriod = $rd->between($monthStart, $monthEnd, true);
        }

        // ACTIVATION month flag. The effective active date is active_date (the
        // start of the current active interval) falling back to begin_date. When
        // it lands AFTER the 1st of THIS row's month, the site was live for only
        // part of the month, so its flat fees (rental / utility) are prorated —
        // drives the "Active <date>" badge on the Period Start cell so users see
        // why a partial month reads less than the full monthly rate (not a bug).
        $effectiveActiveDate = ($this->relationLoaded('customer') && $this->customer)
            ? ($this->customer->active_date ?? $this->customer->begin_date)
            : null;
        $isActivatedInPeriod = false;
        if ($effectiveActiveDate && $monthStart) {
            $ad = $effectiveActiveDate instanceof \Carbon\Carbon
                ? $effectiveActiveDate->copy()->startOfDay()
                : \Carbon\Carbon::parse($effectiveActiveDate)->startOfDay();
            $monthEndForActive = $monthStart->copy()->endOfMonth();
            // gt(monthStart) → after the 1st (a 1st-of-month start = full month,
            // no proration → no badge). lte(monthEnd) → within this row's month.
            $isActivatedInPeriod = $ad->gt($monthStart) && $ad->lte($monthEndForActive);
        }

        // To-date cap for the CURRENT in-progress month. A flat fee should
        // accrue only for the days that have actually elapsed (through this
        // row's period_end = the nightly data cutoff), not bill the whole month
        // up front. Capturing it here lets the flat fee + Vend Earning reflect
        // the month "so far", so aggregated totals stay precise mid-month and
        // Vend Earning (Gross − Net Loc Fee) compares like-for-like against the
        // to-date Gross. Closed months pass NO cap (full month). The cutoff is
        // period_end (not today) so the fee window matches the sales window and
        // self-completes to the full fee at month end.
        $toDateAsOf = null;
        if ($isCurrentMonth && $this->period_end) {
            $toDateAsOf = $this->period_end instanceof \Carbon\Carbon
                ? $this->period_end->copy()
                : \Carbon\Carbon::parse($this->period_end);
        }

        // A MACHINE-SPLIT row (mid-month vend swap) carries a vend_id and covers
        // only part of the month (period_start..period_end). Its flat fee must
        // prorate over the SEGMENT's days, not the whole calendar month — see
        // rowFlatDayRatio. Whole-month rows store vend_id = null. (Contract-change
        // segments carry contract_log_id and keep their stored value below, so
        // they never reach the live re-derivation that uses this ratio.)
        $isMachineSplit = $this->vend_id !== null;

        // Flat-fee proration ratio for this row's month, from the live active
        // window — mirrors the aggregator so an unlocked row shows the same
        // prorated fee the nightly run would store. $flatDayRatio is the
        // to-date figure (capped at period_end for the current month); the
        // uncapped full-month ratio is kept only to detect whether the to-date
        // cap actually reduced the fee (drives the "to date" badge).
        $flatDayRatio = 1.0;
        $flatDayRatioFull = 1.0;
        if ($monthStart && $this->relationLoaded('customer') && $this->customer) {
            $activeDate = $this->customer->active_date ?? $this->customer->begin_date;
            $flatDayRatioFull = \App\Services\CustomerSummaryAggregator::rowFlatDayRatio(
                $activeDate,
                $this->customer->removed_date,
                $monthStart,
                null,
                $isMachineSplit,
                $isCurrentMonth,
                $this->period_start,
                $this->period_end
            );
            $flatDayRatio = $toDateAsOf
                ? \App\Services\CustomerSummaryAggregator::rowFlatDayRatio(
                    $activeDate,
                    $this->customer->removed_date,
                    $monthStart,
                    $toDateAsOf,
                    $isMachineSplit,
                    $isCurrentMonth,
                    $this->period_start,
                    $this->period_end
                )
                : $flatDayRatioFull;
        }

        // Defaults = the frozen per-period snapshot (used as-is for completed
        // months, and as the fallback when the customer relation isn't loaded).
        $contractType = $this->contract_commission_type;
        $contractValue = $this->contract_commission_value !== null ? (float) $this->contract_commission_value : null;
        $contractValue2 = $this->contract_commission_value2 !== null ? (float) $this->contract_commission_value2 : null;
        $contractPsTerm = $this->contract_ps_term !== null ? (float) $this->contract_ps_term : null;
        // Ref Price tier (RP) — frozen at lock time, same rule as the contract
        // terms. Default to the snapshot; whole-month UNLOCKED rows override it
        // live below so a customer RP edit reflects immediately while the row is
        // still open. Resolved value is surfaced as customer.selling_price_type
        // so the badge on Summary.vue freezes once the row is locked.
        $sellingPriceType = $this->contract_selling_price_type;
        $locationFeesCents = (int) $this->location_fees_cents;
        $externalSubsidizeCents = (int) $this->external_subsidize_cents;
        $locationEarningCents = (int) $this->location_earning_cents;
        $locationEarningRate = (float) $this->location_earning_rate;

        // A SEGMENT row (contract_log_id set) was computed under a SPECIFIC
        // historical contract version, not the customer's current one. It must
        // keep its own stored contract + fees — re-deriving live would paint
        // every segment of a split month with the customer's current contract,
        // making them look identical on screen even though each ran under a
        // different deal. Only whole-month rows (no contract_log_id) re-derive
        // live so a contract edit still reflects immediately for them.
        $isSegment = $this->contract_log_id !== null;

        // A RE-ACTIVATED site (removed → active again) has multi-interval
        // log-derived figures stored by the aggregator. Live re-derivation here
        // uses only the customer's single active_date/removed_date pair (latest
        // interval), which would mis-prorate it — so treat it like a frozen row
        // and keep the stored value. Flagged by
        // CustomerController::attachReactivationFlag().
        $isReactivated = (bool) ($this->use_stored_proration ?? false);

        // Set true below when the current-month to-date cap reduced this row's
        // flat fee — surfaced as loc_fee_prorated_to_date for the Summary badge.
        $locFeeProratedToDate = false;

        if (!$isLocked && !$isSegment && !$isReactivated && $this->relationLoaded('customer') && $this->customer) {
            $c = $this->customer;

            // Live contract terms straight off the customer record.
            $contractType = $c->contract_commission_type;
            $contractValue = $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null;
            $contractValue2 = $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null;
            $contractPsTerm = $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null;
            $sellingPriceType = $c->selling_price_type;

            $grossCents = (int) $this->gross_earning_cents;
            $salesCents = (int) $this->sales_cents;
            $gstRatePct = ($c->relationLoaded('operator') && $c->operator && $c->operator->gst_vat_rate !== null)
                ? (float) $c->operator->gst_vat_rate
                : 0.0;

            // Recompute Location Fees with the SAME formula the aggregator uses,
            // but against the live contract terms. sales/gross stay the
            // aggregated (daily) figures — only the contract inputs are live.
            // $flatDayRatio already carries the to-date cap for the current
            // month, so this fee is the amount earned through period_end.
            $locationFeesCents = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                $contractType,
                $contractValue,
                $contractValue2,
                $contractPsTerm,
                $salesCents,
                $grossCents,
                $gstRatePct,
                $flatDayRatio
            );

            // Whether the to-date cap actually shaved the flat fee for this row
            // (only true mid-current-month AND when the contract has a flat
            // component — a pure PS deal has no flat fee to prorate). Compares
            // the to-date fee against the full-month fee; drives the "to date"
            // badge so the reduced figure doesn't read as a bug.
            if ($toDateAsOf && $flatDayRatio < $flatDayRatioFull) {
                $fullMonthFeeCents = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                    $contractType,
                    $contractValue,
                    $contractValue2,
                    $contractPsTerm,
                    $salesCents,
                    $grossCents,
                    $gstRatePct,
                    $flatDayRatioFull
                );
                $locFeeProratedToDate = $fullMonthFeeCents !== $locationFeesCents;
            }

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
            // Removed Date + whether it falls in this row's month. The latter
            // lets the Summary page surface a Lock button on the current
            // in-progress month once the site is being Removed (see the comment
            // at the top of this method).
            'removed_date' => optional($removedDate)->toDateString(),
            'is_removed_in_period' => $isRemovedInPeriod,
            // Effective active date + whether it's a mid-month activation for
            // THIS row's month (flat fees prorated). Drives the "Active <date>"
            // badge on the Period Start cell. See is_activated_in_period above.
            'active_date' => optional($effectiveActiveDate)->toDateString(),
            'is_activated_in_period' => $isActivatedInPeriod,
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
            // Actual payment date from the Paid popup (date-only; defaults to
            // the click date when the popup field was left empty).
            'paid_date' => optional($this->paid_date)->toDateString(),
            'paid_by_user' => $this->relationLoaded('paidBy') && $this->paidBy
                ? ['id' => $this->paidBy->id, 'name' => $this->paidBy->name]
                : null,
            // Site-settlement membership. When set, the row is being paid via a
            // Site Settlement, so it's excluded from the on-summary Mark-Paid /
            // Push flows (mutual exclusion).
            'commission_settlement_id' => $this->commission_settlement_id,
            // Reference of the settlement this row is staged in (when loaded) —
            // drives the yellow "Settlement in progress" badge in the
            // Period Verify & Lock column. Null when not staged / not loaded.
            'settlement_reference' => $this->relationLoaded('commissionSettlement') && $this->commissionSettlement
                ? $this->commissionSettlement->reference
                : null,
            // Admin-set payout override (minor units); null = auto Net Loc Fee.
            'settlement_amount_cents' => $this->settlement_amount_cents,
            // "Waived" state — drives the Waived badge on Customer/Summary.vue.
            // A waived row is still recorded through the Paid flow (is_paid stays
            // true); is_waived only distinguishes waived vs actually paid. Money
            // figures are unaffected.
            'is_waived' => (bool) $this->is_waived,
            'waived_remarks' => $this->waived_remarks,
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
            // True only for the CURRENT in-progress month when the to-date cap
            // shaved a flat fee — the Location Fees / Net Loc Fee figures are
            // accrued through period_end, not the full month. Drives the small
            // "to date" badge on Summary.vue (with the day fraction below) so
            // the reduced amount is clearly intentional. Self-clears to false
            // at month end (full fee) and for pure-PS / closed / locked rows.
            'loc_fee_prorated_to_date' => $locFeeProratedToDate,
            // Billable days so far = the actual to-date ratio × month length, so
            // the badge matches the fee even when the site also activated /was
            // removed mid-month (those days are excluded by the same ratio),
            // rather than naively showing elapsed calendar days.
            'to_date_days' => $locFeeProratedToDate && $monthStart
                ? (int) round($flatDayRatio * $monthStart->daysInMonth)
                : null,
            'month_total_days' => $locFeeProratedToDate && $monthStart
                ? (int) $monthStart->daysInMonth
                : null,
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
            // "Avg Mthly Sales" — cumulative running average of monthly sales
            // (sales_cents) for this customer up to and including this row's
            // month. Live for the current month, frozen once a month completes.
            // Attached by CustomerController::attachAccumulatedVendingEarning().
            'avg_monthly_sales_cents' => isset($this->avg_monthly_sales_cents)
                ? (int) $this->avg_monthly_sales_cents
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
            // Pending "upcoming term" (a future contract queued via "Set
            // Upcoming Term", awaiting its effective date) — drives the amber
            // "Upcoming Term" badge in the Site column. Null when none pending.
            // Attached by CustomerController::attachUpcomingTermFlag().
            'upcoming_term' => $this->upcoming_term ?? null,
            // Machine-split info (mid-month vend swap). machine_vend is this
            // row's machine ([id, code]) on a split row, null on whole-month
            // rows (Vue falls back to the site's current vend). is_new_machine
            // marks the swapped-in row for the "New" badge. Attached by
            // CustomerController::attachMachineSplitInfo().
            'vend_id' => $this->vend_id,
            'machine_vend' => $this->machine_vend ?? null,
            'is_new_machine' => (bool) ($this->is_new_machine ?? false),
            // True on the PREVIOUS (swapped-out) segment of a mid-month machine
            // replacement — its period_end is the swap boundary (last billable
            // day on that machine). Drives the red period-end highlight on the
            // Summary page, mirroring the removal-date styling. Set by
            // CustomerController::attachMachineSplitInfo().
            'is_replaced_machine' => (bool) ($this->is_replaced_machine ?? false),
            // The site's most-recent ("Current") row. Only this row stays
            // hyperlinked + editable (Site Name, Machine ID, Cust Note, Remarks);
            // older rows render frozen/read-only. Set by attachMachineSplitInfo.
            'is_latest_row' => (bool) ($this->is_latest_row ?? false),
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
                    // Primary contact (morphOne). `name` renders the "Billing
                    // Contact Person" sub-line; `company` (the Edit form's
                    // "Bill From" field) renders the "Billing Company" sub-line
                    // stacked under the Address column on the Summary page.
                    'contact' => $c->relationLoaded('contact') && $c->contact
                        ? [
                            'name' => $c->contact->name,
                            'company' => $c->contact->company,
                        ]
                        : null,
                    'virtual_customer_code' => $c->virtual_customer_code,
                    'virtual_customer_prefix' => $c->virtual_customer_prefix,
                    'person_id' => $c->person_id ?? null,
                    'is_active' => (bool) $c->is_active,
                    // Site Status (customers.status_id) — drives the colored
                    // status badge in the Site column on the Summary page.
                    // Name resolved via STATUSES_MAPPING so the Vue side
                    // doesn't need its own id→label copy.
                    'status_id' => $c->status_id,
                    'status_name' => \App\Models\Customer::STATUSES_MAPPING[$c->status_id] ?? null,
                    // Lock-aware RP: frozen snapshot for locked rows, live for
                    // unlocked. Falls back to the live customer value for rows
                    // locked before contract_selling_price_type existed, so the
                    // "RPx" badge never blanks on legacy locked months.
                    'selling_price_type' => $sellingPriceType ?? $c->selling_price_type,
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
                    // Dedicated "Remarks for Loc Fees" — one per Site, parked on
                    // the customer record (same as notes). Rendered in the
                    // rightmost column on the Summary page; sortable by the
                    // updated_at timestamp. No unread tracking. See migration
                    // 2026_06_20_000000_add_loc_fee_remarks_to_customers.
                    'loc_fee_remarks' => $c->loc_fee_remarks,
                    'loc_fee_remarks_updated_at' => optional($c->loc_fee_remarks_updated_at)->toDateTimeString(),
                    'loc_fee_remarks_updated_by_user' => $c->relationLoaded('locFeeRemarksUpdatedBy') && $c->locFeeRemarksUpdatedBy ? [
                        'id' => $c->locFeeRemarksUpdatedBy->id,
                        'name' => $c->locFeeRemarksUpdatedBy->name,
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
