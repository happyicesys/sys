<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks API invoices created from the Customer Management > Summary page.
 *
 * One row per (customer, period_start, period_end) invocation — re-creation
 * is allowed, so a customer can have multiple rows for the same period.
 * The UI surfaces the latest one as the "did this period already invoice?"
 * indicator.
 *
 * See migration 2026_05_10_120000_create_customer_period_summary_invoices_table
 * for column docs.
 */
class CustomerPeriodSummaryInvoice extends Model
{
    protected $table = 'customer_period_summary_invoices';

    protected $fillable = [
        'customer_id',
        'period_start',
        'period_end',
        'contract_commission_type',
        'cms_transaction_id',
        'cms_transaction_at',
        'cms_transaction_by',
        'total_amount_cents',
        'summary_snapshot',
        'payload',
        'response',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'cms_transaction_at' => 'datetime',
        'total_amount_cents' => 'integer',
        'summary_snapshot' => 'array',
        'payload' => 'array',
        'response' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cmsTransactionBy()
    {
        return $this->belongsTo(User::class, 'cms_transaction_by');
    }
}
