<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Doubles as the legacy on-the-fly bank batch (is_settlement = false) AND the new
 * "Refund Settlement" object (is_settlement = true) with a dated, per-payout-group
 * open -> closed -> exported -> done lifecycle.
 */
class RefundPayoutBatch extends Model
{
    use HasFactory;

    // Legacy direct-export batch statuses.
    const STATUS_GENERATED = 'generated';
    const STATUS_UPLOADED = 'uploaded';

    // Settlement lifecycle statuses.
    const STATUS_OPEN = 'open';         // collecting approved tickets
    const STATUS_CLOSED = 'closed';     // admin locked the pool, ready to export
    const STATUS_EXPORTED = 'exported'; // a bank/worklist file was generated
    const STATUS_DONE = 'done';         // every member ticket completed

    protected $fillable = [
        'reference',
        'is_settlement',
        'settlement_date',
        'payout_group_id',
        'operator_id',
        'sequence',
        'method',
        'created_by',
        'csv_path',
        'count',
        'total_cents',
        'status',
        'uploaded_at',
        'closed_by',
        'closed_at',
        'exported_by',
        'exported_at',
    ];

    protected $casts = [
        'is_settlement' => 'boolean',
        'settlement_date' => 'date',
        'count' => 'integer',
        'total_cents' => 'integer',
        'sequence' => 'integer',
        'uploaded_at' => 'datetime',
        'closed_at' => 'datetime',
        'exported_at' => 'datetime',
    ];

    public function tickets()
    {
        return $this->hasMany(RefundTicket::class, 'payout_batch_id');
    }

    public function payoutGroup()
    {
        return $this->belongsTo(PayoutGroup::class, 'payout_group_id');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class, 'operator_id');
    }

    public function settlementLogs()
    {
        return $this->hasMany(RefundSettlementLog::class, 'refund_payout_batch_id')->latest('id');
    }

    public function exports()
    {
        return $this->hasMany(RefundSettlementExport::class, 'refund_payout_batch_id')->latest('id');
    }

    /** Only true "Refund Settlement" rows (not legacy direct-export batches). */
    public function scopeSettlements($query)
    {
        return $query->where('is_settlement', true);
    }

    /** Is this settlement's day already past (stale, still open)? */
    public function isStale(): bool
    {
        return $this->status === self::STATUS_OPEN
            && $this->settlement_date
            && $this->settlement_date->isPast()
            && !$this->settlement_date->isToday();
    }
}
