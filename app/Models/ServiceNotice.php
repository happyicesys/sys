<?php

namespace App\Models;

use App\Models\Concerns\IsOpsJobStop;
use Illuminate\Database\Eloquent\Model;

/**
 * A repair stop inside an ops job, against one machine. See
 * SERVICE_NOTICE_PLAN_2026-09-19.md.
 */
class ServiceNotice extends Model
{
    use IsOpsJobStop;

    public const CODE_PREFIX = 'SN';

    public const STOP_TYPE = 'service_notice';

    public const STATUS_PENDING = 1;

    public const STATUS_COMPLETED = 3;

    public const STATUS_CANCELLED = 99;

    public const STATUS_MAPPINGS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'code', 'operator_id', 'ops_job_id', 'vend_id', 'customer_id', 'sequence', 'status', 'remarks',
        'completed_at', 'completed_by', 'undo_completed_at', 'undo_completed_by',
        'cancelled_at', 'cancelled_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'status' => 'integer',
        'sequence' => 'float',
        'completed_at' => 'datetime',
        'undo_completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(ServiceNoticeItem::class)->orderBy('sequence')->orderBy('id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * The cms rule, kept: a notice can only be completed once every item has
     * been given a verdict (done, incomplete or cancelled).
     */
    public function hasUnresolvedItems(): bool
    {
        return $this->items()->where('status', ServiceNoticeItem::STATUS_NEW)->exists();
    }
}
