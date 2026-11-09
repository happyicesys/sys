<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A camera-video push from the smart-freezer supplier (Zijia). Written by ZijiaVideoWebhookController
 * (`raw_body` is the untouched request body, `payload` its parsed form) and by EarlyOrderVideo, which
 * finds the same files on their storage minutes before the push: its rows carry
 * `payload.source = mark1_early_fetch` and no raw body.
 */
class SmartFreezerVideo extends Model
{
    public const SOURCE_EARLY_FETCH = 'mark1_early_fetch';

    /**
     * Every stored video, pushed by Zijia or found early by mark1, gets our own private copy
     * (ZijiaVideoCopier, Brian 2026-11-09). Queued, so a slow download never holds up the push;
     * never allowed to fail the write (the sync driver runs it inline).
     */
    protected static function booted(): void
    {
        static::created(function (self $video) {
            if (! config('smart_freezer.zijia.video_copy.enabled') || array_filter((array) $video->video_urls) === []) {
                return;
            }
            try {
                \App\Jobs\CopyZijiaVideo::dispatch($video->id)->delay(now()->addSeconds(30));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    protected $fillable = [
        'supplier',
        'vend_id',
        'smart_freezer_recognition_id',
        'order_no',
        'device_id',
        'video_urls',
        'payload',
        'raw_body',
        'content_type',
        'source_ip',
    ];

    protected $casts = [
        'video_urls' => 'array',
        'payload' => 'array',
    ];

    /** Found by mark1 on Zijia's storage, not pushed by them (a push always has its raw body). */
    public function isEarlyFetch(): bool
    {
        return $this->raw_body === null && (((array) $this->payload)['source'] ?? null) === self::SOURCE_EARLY_FETCH;
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class)->withoutGlobalScopes();
    }

    /** Our own copies of this push's video files (ZijiaVideoCopier). */
    public function copies()
    {
        return $this->hasMany(SmartFreezerVideoCopy::class);
    }

    /** The door session's AI recognition this push belongs to. */
    public function recognition()
    {
        return $this->belongsTo(SmartFreezerRecognition::class, 'smart_freezer_recognition_id');
    }
}
