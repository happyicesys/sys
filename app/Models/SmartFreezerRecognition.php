<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One AI recognition of one freezer door session (Zijia's algorithm service).
 *
 * Lifecycle: `pending` (waiting — `status_reason` says for what) → `submitting` (claimed, so a
 * metered call is never made twice) → `submitted` (their `request_id`) → `completed` with a
 * `verdict`, or `failed` with the reason. Written only by FreezerRecognitionService.
 */
class SmartFreezerRecognition extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUBMITTING = 'submitting';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'vend_id', 'trade_id', 'session_ref', 'device_id', 'vend_transaction_id',
        'status', 'status_reason', 'request_id', 'request_payload', 'response',
        'order_status', 'items', 'error_message', 'callback_payload', 'callback_verified',
        'verdict', 'verdict_lines', 'submitted_at', 'completed_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response' => 'array',
        'items' => 'array',
        'callback_payload' => 'array',
        'callback_verified' => 'boolean',
        'verdict_lines' => 'array',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function vend(): BelongsTo
    {
        return $this->belongsTo(Vend::class)->withoutGlobalScopes();
    }

    /** The video pushes of this door session — one, or one per camera. */
    public function videos(): HasMany
    {
        return $this->hasMany(SmartFreezerVideo::class)->orderBy('id');
    }

    public function vendTransaction(): BelongsTo
    {
        return $this->belongsTo(VendTransaction::class)->withoutGlobalScopes();
    }
}
