<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pre-aggregated per-customer per-month summary feeding the
 * Customer Management > Summary page.
 *
 * Refreshed nightly by ProcessCustomerSummaryMonth.
 */
class CustomerPeriodSummary extends Model
{
    protected $table = 'customer_period_summaries';

    protected $fillable = [
        'customer_id',
        'operator_id',
        'year_month',
        'period_start',
        'period_end',
        'is_current_month',
        'segment_index',
        'segmentation_overridden',
        'contract_log_id',
        // Per-row machine. Set only on machine-split rows (a mid-month vend
        // swap); null on whole-month rows (display falls back to the site's
        // current vend). See migration 2026_06_24_000000.
        'vend_id',
        'as_of_date',
        'sales_cents',
        'gross_earning_cents',
        'location_fees_cents',
        'location_earning_cents',
        'location_earning_rate',
        // Per-period snapshot of the External Subsidize amount (cents).
        // location_earning_cents is stored NET of this. See migration
        // 2026_05_25_010000_add_external_subsidize_cents_to_customer_period_summaries.
        'external_subsidize_cents',
        'transaction_count',
        'vend_count',
        'job_count',
        'contract_commission_type',
        'contract_commission_value',
        'contract_commission_value2',
        'contract_ps_term',
        // Ref Price tier (RP) snapshot — frozen at lock time alongside the
        // contract terms so a later customers.selling_price_type change does
        // NOT rewrite the badge on an already-locked month. See migration
        // add_contract_selling_price_type_to_customer_period_summaries.
        'contract_selling_price_type',
        // Action-triggered lock (see migration add_lock_to_customer_period_summaries).
        'locked_at',
        'locked_by',
        // Explicit boolean state flags — mirror locked_at / paid_at (which
        // stay the audit source of truth). Synced at every lock/unlock/
        // paid/unpaid writer; backfilled by BackfillSummaryLockedPaidFlagsSeeder.
        'is_locked',
        'is_paid',
        // "Waived" state — set via the Mark as Paid / Waived popup. is_waived
        // flags the location fee as waived (rather than actually paid);
        // waived_remarks is the mandatory reason. Both ride alongside the
        // paid_* columns and are cleared on Unpaid. Money figures unaffected.
        'is_waived',
        'waived_remarks',
        // Paid + audit timestamps (see migration add_paid_and_audit_to_customer_period_summaries).
        'paid_at',
        'paid_date',
        'paid_by',
        // Site-settlement membership (which commission_settlements batch this row
        // was pushed into). Retained after paid as the audit link.
        'commission_settlement_id',
        'last_unpaid_at',
        'last_unpaid_by',
        'last_unlocked_at',
        'last_unlocked_by',
        // "Email Performance Report" audit on a locked row — set when the
        // modal's Email button is clicked. The actual email is composed by
        // the operator's own mail client via mailto; the server only records
        // who clicked + when. See migration 2026_05_28_020000.
        'report_emailed_at',
        'report_emailed_by',
    ];

    protected $casts = [
        'year_month' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'as_of_date' => 'date',
        'is_current_month' => 'boolean',
        'segment_index' => 'integer',
        'segmentation_overridden' => 'boolean',
        'location_earning_rate' => 'decimal:4',
        'contract_commission_value' => 'decimal:2',
        'contract_commission_value2' => 'decimal:2',
        'contract_ps_term' => 'decimal:2',
        'locked_at' => 'datetime',
        'is_locked' => 'boolean',
        'is_paid' => 'boolean',
        'is_waived' => 'boolean',
        'paid_at' => 'datetime',
        'paid_date' => 'date',
        'last_unpaid_at' => 'datetime',
        'last_unlocked_at' => 'datetime',
        'report_emailed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * User who locked this row (null when unlocked). Drives the "Locked by X
     * at Y" tooltip in the Lock column on the Customer Summary page.
     */
    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * User who marked this row Paid (null when unpaid). Drives the "Paid by X
     * at Y" tooltip.
     */
    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * User who last marked this row Unpaid (the most recent Paid → Unpaid
     * transition). Kept after the row goes paid again, so the audit trail
     * survives Lock → Paid → Unpaid → Paid cycles.
     */
    public function lastUnpaidBy()
    {
        return $this->belongsTo(User::class, 'last_unpaid_by');
    }

    /**
     * User who last unlocked this row (most recent Lock → Unlock transition).
     */
    public function lastUnlockedBy()
    {
        return $this->belongsTo(User::class, 'last_unlocked_by');
    }

    /**
     * User who last clicked the "Email Performance Report" button in the
     * Report Content modal (null if never). Powers the "Last sent by X at Y"
     * audit line shown inside the modal.
     */
    public function reportEmailedBy()
    {
        return $this->belongsTo(User::class, 'report_emailed_by');
    }
}
