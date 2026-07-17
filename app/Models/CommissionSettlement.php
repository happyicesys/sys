<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Site (commission / location-fee) settlement batch. Members are
 * customer_period_summaries rows linked by commission_settlement_id.
 * Lifecycle: open → closed (mirrors the refund settlement).
 */
class CommissionSettlement extends Model
{
    use HasFactory;

    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'reference',
        'settlement_date',
        'payout_group_id',
        'operator_id',
        'sequence',
        'status',
        'count',
        'total_cents',
        'created_by',
        'closed_by',
        'closed_at',
        'exported_by',
        'exported_at',
        'csv_path',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'count' => 'integer',
        'total_cents' => 'integer',
        'sequence' => 'integer',
        'closed_at' => 'datetime',
        'exported_at' => 'datetime',
    ];

    public function summaries()
    {
        return $this->hasMany(CustomerPeriodSummary::class, 'commission_settlement_id');
    }

    public function payoutGroup()
    {
        return $this->belongsTo(PayoutGroup::class, 'payout_group_id');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class, 'operator_id');
    }

    public function logs()
    {
        return $this->hasMany(CommissionSettlementLog::class, 'commission_settlement_id')->latest('id');
    }

    public function exports()
    {
        return $this->hasMany(CommissionSettlementExport::class, 'commission_settlement_id')->latest('id');
    }

    public function isStale(): bool
    {
        return $this->status === self::STATUS_OPEN
            && $this->settlement_date
            && $this->settlement_date->isPast()
            && !$this->settlement_date->isToday();
    }
}
