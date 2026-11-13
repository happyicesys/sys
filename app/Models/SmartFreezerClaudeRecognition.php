<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Claude's count of one freezer door session, made beside Zijia's (Brian, 2026-11-13: "send a copy to you…
 * so eventually we can compare whether Zijia's one is reliable or yours"). Written only by
 * App\Services\SmartFreezer\Claude\ClaudeShadowRecognitionService. SHADOW ONLY: nothing reads it to charge,
 * release, refund or move stock.
 *
 * Lifecycle: `pending` → `running` (claimed, so a billed call is never made twice) → `done` with `items`
 * (product id => units out of the cabinet) and a `verdict` once the sale is found, or `failed` / `skipped` with
 * the reason. `cost_usd_micros` is what the call cost in millionths of a US dollar, from the usage the API
 * returned and the price table in config `smart_freezer.claude_recognition.prices`.
 */
class SmartFreezerClaudeRecognition extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $guarded = ['id'];

    protected $casts = [
        'frames' => 'array',
        'items' => 'array',
        'answer' => 'array',
        'verdict_lines' => 'array',
        'needs_review' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function recognition(): BelongsTo
    {
        return $this->belongsTo(SmartFreezerRecognition::class, 'smart_freezer_recognition_id');
    }

    public function vend(): BelongsTo
    {
        return $this->belongsTo(Vend::class)->withoutGlobalScopes();
    }

    /** The call's cost in Singapore cents, rounded half up, at the configured rate. */
    public function costSgdCents(): ?float
    {
        if ($this->cost_usd_micros === null) {
            return null;
        }

        return round($this->cost_usd_micros / 10_000 * (float) config('smart_freezer.claude_recognition.usd_to_sgd'), 2);
    }
}
