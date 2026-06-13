<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single pending future placement-contract change for a customer.
 *
 * See migration 2026_06_13_000000_create_customer_scheduled_contracts_table
 * for the lifecycle. The daily `contract:apply-scheduled` command turns a
 * `pending` row into the customer's live contract on its effective_date.
 */
class CustomerScheduledContract extends Model
{
    protected $table = 'customer_scheduled_contracts';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'customer_id',
        'effective_date',
        'status',
        'applied_at',
        'contract_commission_type',
        'contract_commission_value',
        'contract_commission_value2',
        'contract_ps_term',
        'is_external_subsidize',
        'external_subsidize_amount',
        'contract_from',
        'contract_until',
        'contract_auto_renewal',
        'contract_notice_period',
        'contract_remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'applied_at' => 'datetime',
        'contract_commission_value' => 'decimal:2',
        'contract_commission_value2' => 'decimal:2',
        'contract_ps_term' => 'decimal:2',
        'is_external_subsidize' => 'boolean',
        'external_subsidize_amount' => 'decimal:2',
        'contract_from' => 'date',
        'contract_until' => 'date',
        'contract_auto_renewal' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
