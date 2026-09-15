<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One remote cabinet control sent to a smart freezer and its answer.
 *
 * @see \App\Services\Freezer\FreezerControlService
 */
class FreezerControlCommand extends Model
{
    public const STATUS_PENDING = 'pending';

    /** Shown, never stored: a pending row whose frame expired with no answer. */
    public const STATUS_TIMEOUT = 'timeout';

    /** Results the device may send back in FREEZERCTLACK.result. */
    public const RESULTS = ['ok', 'refused', 'indeterminate', 'unsupported', 'busy', 'invalid', 'expired', 'duplicate', 'error'];

    protected $fillable = [
        'vend_id', 'cmd_id', 'op', 'args', 'status', 'response_msg',
        'requested_by', 'requested_by_name', 'ip', 'expires_at', 'responded_at',
    ];

    protected $casts = [
        'args' => 'array',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function vend(): BelongsTo
    {
        return $this->belongsTo(Vend::class);
    }

    /**
     * The status to show: a pending row past its expiry plus a grace period reads as timeout.
     * Computed rather than written, so no scheduler is needed and a late ack still lands.
     */
    public function displayStatus(?Carbon $now = null): string
    {
        $now ??= Carbon::now();
        if ($this->status === self::STATUS_PENDING && $this->expires_at && $now->greaterThan($this->expires_at->copy()->addSeconds(30))) {
            return self::STATUS_TIMEOUT;
        }

        return $this->status;
    }
}
