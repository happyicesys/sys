<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only history of customer placement-contract values.
 *
 * - effective_to = null marks the row as currently active.
 * - When a new version is written, the previously-active row's effective_to
 *   is stamped to the same moment as the new row's effective_from.
 */
class CustomerContractLog extends Model
{
    protected $table = 'customer_contract_logs';

    protected $fillable = [
        'customer_id',
        'effective_from',
        'effective_to',
        'contract_commission_type',
        'contract_commission_value',
        'contract_commission_value2',
        'contract_ps_term',
        'contract_until',
        'contract_auto_renewal',
        'contract_min_commitment_period',
        'contract_notice_period',
        'changed_by',
        'source',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'contract_commission_value' => 'decimal:2',
        'contract_commission_value2' => 'decimal:2',
        'contract_ps_term' => 'decimal:2',
        'contract_until' => 'date',
        'contract_auto_renewal' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
