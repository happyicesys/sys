<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Our private copy (DO Spaces) of one Zijia door-session video file. Written only by
 * App\Services\SmartFreezer\ZijiaVideoCopier; one row per video URL (`url_hash`).
 */
class SmartFreezerVideoCopy extends Model
{
    public const STATUS_COPIED = 'copied';

    public const STATUS_FAILED = 'failed';

    protected $guarded = ['id'];

    protected $casts = [
        'copied_at' => 'datetime',
    ];

    public static function hashOf(string $url): string
    {
        return sha1($url);
    }

    public function video()
    {
        return $this->belongsTo(SmartFreezerVideo::class, 'smart_freezer_video_id');
    }

    public function isCopied(): bool
    {
        return $this->status === self::STATUS_COPIED && $this->path !== null;
    }
}
