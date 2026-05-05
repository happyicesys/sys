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
        'transaction_count',
        'vend_count',
        'contract_commission_type',
        'contract_commission_value',
        'contract_commission_value2',
        'contract_ps_term',
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
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }
}
