<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One thing to fix on a service notice: what, how it looked before, how it
 * looked after, and the verdict. Status codes are the cms ones on purpose —
 * staff already speak them.
 */
class ServiceNoticeItem extends Model
{
    public const STATUS_NEW = 1;

    public const STATUS_COMPLETED = 2;

    public const STATUS_INCOMPLETE = 90;

    public const STATUS_CANCELLED = 99;

    public const STATUS_MAPPINGS = [
        self::STATUS_NEW => 'New',
        self::STATUS_COMPLETED => 'Completed 完成',
        self::STATUS_INCOMPLETE => 'Incomplete 未能完成',
        self::STATUS_CANCELLED => 'Cancelled 取消',
    ];

    /** attachments.type — which column of the item a file belongs to. */
    public const SLOT_DESCRIPTION = 1;

    public const SLOT_BEFORE = 2;

    public const SLOT_AFTER = 3;

    public const SLOTS = [self::SLOT_DESCRIPTION, self::SLOT_BEFORE, self::SLOT_AFTER];

    protected $fillable = [
        'service_notice_id', 'sequence', 'status', 'desc', 'desc_before', 'desc_after',
        'incomplete_reason', 'status_changed_at', 'status_changed_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'status' => 'integer',
        'sequence' => 'integer',
        'status_changed_at' => 'datetime',
    ];

    public function serviceNotice()
    {
        return $this->belongsTo(ServiceNotice::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->oldest();
    }

    public function statusChangedBy()
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function statusName(): string
    {
        return self::STATUS_MAPPINGS[(int) $this->status] ?? 'New';
    }
}
