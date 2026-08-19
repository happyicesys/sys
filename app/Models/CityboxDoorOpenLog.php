<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only record of every chiller door-open request. No update path by design. */
class CityboxDoorOpenLog extends Model
{
    public const SOURCE_OPS_JOB_PAGE = 'ops_job_page';

    public const SOURCE_OPS_JOB_ITEM_PAGE = 'ops_job_item_page';

    public const SOURCE_VEND_SETTINGS = 'vend_settings';

    public const RESULT_OPENED = 'opened';

    public const RESULT_REFUSED = 'refused';

    public const RESULT_ERROR = 'error';

    protected $fillable = [
        'vend_id', 'citybox_equipment_id', 'ops_job_item_id', 'ops_job_id', 'user_id', 'source', 'requested_at',
        'result', 'msg_id', 'open_log_id', 'citybox_code', 'citybox_message', 'device_state_before', 'ip', 'user_agent',
    ];

    protected $casts = ['requested_at' => 'datetime'];

    public function vend(): BelongsTo
    {
        return $this->belongsTo(Vend::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opsJobItem(): BelongsTo
    {
        return $this->belongsTo(OpsJobItem::class);
    }

    public function scopeOpened(Builder $q): Builder
    {
        return $q->where('result', self::RESULT_OPENED);
    }
}
