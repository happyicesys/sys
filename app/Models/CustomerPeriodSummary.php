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
        // Action-triggered lock (see migration add_lock_to_customer_period_summaries).
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'year_month' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'as_of_date' => 'date',
        'is_current_month' => 'boolean',
        'location_earning_rate' => 'decimal:4',
        'contract_commission_value' => 'decimal:2',
        'contract_commission_value2' => 'decimal:2',
        'contract_ps_term' => 'decimal:2',
        'locked_at' => 'datetime',
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
}
