<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail for the settlement ledger (Payment History).
 * One row per money action: payment recorded, waiver, reversal, or an edit to
 * an opening balance / adjustment. See migration
 * 2026_06_25_000002_create_customer_settlement_logs_table.
 */
class CustomerSettlementLog extends Model
{
    protected $table = 'customer_settlement_logs';

    // Actions.
    public const ACTION_PAYMENT          = 'payment';
    public const ACTION_WAIVER           = 'waiver';
    public const ACTION_PAYMENT_REVERSED = 'payment_reversed';
    public const ACTION_EDITED           = 'edited';
    public const ACTION_CREATED          = 'created';
    public const ACTION_DELETED          = 'deleted';

    protected $fillable = [
        'customer_id',
        'customer_settlement_id',
        'reference_no',
        'action',
        'entry_type',
        'old_amount_cents',
        'new_amount_cents',
        'note',
        'changed_by',
        'source',
    ];

    protected $casts = [
        'old_amount_cents' => 'integer',
        'new_amount_cents' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function settlement()
    {
        return $this->belongsTo(CustomerSettlement::class, 'customer_settlement_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
