<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Work-queue row for the "freeze 10 min after stock-in" job.
 *
 * One row per ops_job_item that is waiting to be frozen. Written by
 * OpsJobItemObserver (enqueue on eligible, delete when no longer eligible) and
 * consumed by the ops:freeze-stock-in command, which deletes the row once the
 * item is frozen. See the create_ops_job_item_freeze_queue_table migration.
 */
class OpsJobItemFreezeQueue extends Model
{
    protected $table = 'ops_job_item_freeze_queue';

    protected $fillable = [
        'ops_job_item_id',
        'eligible_at',
    ];

    protected $casts = [
        'eligible_at' => 'datetime',
    ];

    public function opsJobItem()
    {
        return $this->belongsTo(OpsJobItem::class, 'ops_job_item_id');
    }
}
