<?php

namespace App\Models;

use App\Models\Concerns\IsOpsJobStop;
use Illuminate\Database\Eloquent\Model;

/**
 * "Stock Count" in the UI: a spot check of a drawn sample of one machine's
 * channels, as a stop inside an ops job. Unrelated to StockCount (the nightly
 * valuation snapshot). See STOCK_CHECK_PLAN_2026-09-19.md.
 */
class StockCheck extends Model
{
    use IsOpsJobStop;

    public const CODE_PREFIX = 'SC';

    public const STOP_TYPE = 'stock_check';

    public const STATUS_PENDING = 1;

    public const STATUS_COMPLETED = 3;

    public const STATUS_CANCELLED = 99;

    public const STATUS_MAPPINGS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_COMPLETED => 'Counted',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'code', 'operator_id', 'ops_job_id', 'vend_id', 'customer_id', 'sequence', 'status',
        'is_random', 'sample_size', 'product_filter', 'remarks',
        'counted_at', 'counted_by', 'undo_counted_at', 'undo_counted_by',
        'synced_at', 'synced_by', 'cancelled_at', 'cancelled_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'status' => 'integer',
        'sequence' => 'float',
        'is_random' => 'boolean',
        'sample_size' => 'integer',
        'product_filter' => 'array',
        'counted_at' => 'datetime',
        'undo_counted_at' => 'datetime',
        'synced_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function channels()
    {
        return $this->hasMany(StockCheckChannel::class)->orderBy('vend_channel_code');
    }

    public function countedBy()
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function syncedBy()
    {
        return $this->belongsTo(User::class, 'synced_by');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->oldest();
    }

    /** A check with any channel already applied to the machine qty is history. */
    public function hasSyncedChannels(): bool
    {
        return $this->channels()->whereNotNull('synced_at')->exists();
    }
}
