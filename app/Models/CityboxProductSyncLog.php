<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One catalog/device sync run: counts + which ids changed. Append-only. */
class CityboxProductSyncLog extends Model
{
    public const SOURCE_CATALOG_SCHEDULED = 'catalog_scheduled';

    public const SOURCE_CATALOG_MANUAL = 'catalog_manual';

    public const SOURCE_DEVICE_POLL = 'device_poll';

    protected $fillable = [
        'source', 'triggered_by', 'started_at', 'finished_at',
        'fetched', 'added', 'updated', 'delisted', 'unchanged', 'details_json', 'error',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'details_json' => 'array',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /** Human summary for flashes: "2 new, 1 updated, 0 delisted". */
    public function summaryLine(): string
    {
        return sprintf('%d new, %d updated, %d delisted', $this->added, $this->updated, $this->delisted);
    }
}
