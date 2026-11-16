<?php

namespace App\Services\Commission;

use App\Models\CustomerPeriodSummary;
use App\Models\CustomerSettlement;
use Carbon\Carbon;

/**
 * Posts a removed site's location fee for its LAST month as soon as that month
 * is locked, instead of waiting for settlement:generate-monthly on the 1st of
 * the next month (Brian, 2026-11-16: "after I lock, Payment History does not
 * show the Nov26 comm"). A site removed mid-month may be locked early (see
 * CustomerController::summaryRemovedInPeriod), and staff then pay or waive that
 * month at once; without the charge the waiver had nothing to cancel.
 *
 * Same figure and rules as the monthly job: Net Loc Fee = SUM(location_fees_cents)
 * − SUM(external_subsidize_cents) over the customer's rows for the month, posted
 * only when positive (we owe the site), once per customer + month. It waits until
 * EVERY row of that customer-month is locked, so a machine-split month never posts
 * a part. The monthly job later sees the row and skips the customer.
 */
class RemovedSiteLocationFeeAccrual
{
    /**
     * Post the month's location fee if all its rows are locked and none is posted.
     * Returns the new ledger row, or null when nothing was posted.
     */
    public function accrue(CustomerPeriodSummary $summary): ?CustomerSettlement
    {
        if (! $summary->customer_id || ! $summary->year_month) {
            return null;
        }
        $monthStart = Carbon::parse($summary->year_month)->startOfMonth();
        $ymDate = $monthStart->toDateString();

        $rows = CustomerPeriodSummary::query()
            ->where('customer_id', $summary->customer_id)
            ->where('year_month', $ymDate)
            ->get(['id', 'operator_id', 'locked_at', 'location_fees_cents', 'external_subsidize_cents']);

        if ($rows->isEmpty() || $rows->contains(fn ($r) => $r->locked_at === null)) {
            return null;
        }

        $net = (int) $rows->sum('location_fees_cents') - (int) $rows->sum('external_subsidize_cents');
        if ($net <= 0) {
            return null;
        }

        $exists = CustomerSettlement::query()
            ->where('customer_id', $summary->customer_id)
            ->where('entry_type', CustomerSettlement::TYPE_LOCATION_FEE)
            ->where('year_month', $ymDate)
            ->exists();
        if ($exists) {
            return null;
        }

        // Dated on the Removed Date (the fee's last day) when it is in this month,
        // else the month's last day like the monthly job.
        $removed = $summary->customer?->removed_date;
        $entryDate = $removed && Carbon::parse($removed)->between($monthStart, $monthStart->copy()->endOfMonth())
            ? Carbon::parse($removed)->toDateString()
            : $monthStart->copy()->endOfMonth()->toDateString();

        return CustomerSettlement::create([
            'customer_id' => $summary->customer_id,
            'operator_id' => $rows->max('operator_id'),
            'entry_date' => $entryDate,
            'year_month' => $ymDate,
            'entry_type' => CustomerSettlement::TYPE_LOCATION_FEE,
            'amount_cents' => $net,
            'item' => 'Location Fees '.$monthStart->format('F Y'),
            'remarks' => null,
            'source' => CustomerSettlement::SOURCE_REMOVED_LOCK,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Unlocking the month before it ends takes the early charge back out, so the
     * monthly job posts the final figure on the 1st. After the month has ended the
     * monthly job has already skipped this customer, so the row stays — like any
     * posted location fee.
     */
    public function retract(CustomerPeriodSummary $summary): int
    {
        if (! $summary->customer_id || ! $summary->year_month) {
            return 0;
        }
        $monthStart = Carbon::parse($summary->year_month)->startOfMonth();
        if ($monthStart->copy()->endOfMonth()->endOfDay()->isPast()) {
            return 0;
        }

        return CustomerSettlement::query()
            ->where('customer_id', $summary->customer_id)
            ->where('entry_type', CustomerSettlement::TYPE_LOCATION_FEE)
            ->where('year_month', $monthStart->toDateString())
            ->where('source', CustomerSettlement::SOURCE_REMOVED_LOCK)
            ->get()
            ->each->delete()
            ->count();
    }
}
