<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * ApkRelease — one uploaded, signed Android build offered to a fleet via OTA.
 *
 * Releases are partitioned by CHANNEL (see config/ota.php): the legacy vending APK
 * and the smart-freezer APK are separate products with separate versionCode
 * sequences, so every query here is channel-scoped. version_code is unique per
 * channel, never globally.
 *
 * The "live" manifest for a channel is the highest version_code row in that channel
 * with status = published (see scopeLiveManifest). Ramping a rollout = editing
 * rollout_permille; rolling back = publishing a HIGHER version_code carrying the
 * previous good code, because Android refuses downgrades — rollback is always
 * "roll forward".
 *
 * @see \App\Http\Controllers\OtaController  device-facing manifest
 * @see \App\Http\Controllers\ApkReleaseController  admin UI
 */
class ApkRelease extends Model
{
    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'channel',
        'package_name',
        'version_code',
        'version_name',
        'file_url',
        'file_path',
        'sha256',
        'size_bytes',
        'mandatory',
        'min_supported_version_code',
        'rollout_permille',
        'status',
        'release_notes',
        'uploaded_by',
    ];

    protected $casts = [
        'version_code' => 'integer',
        'size_bytes' => 'integer',
        'mandatory' => 'boolean',
        'min_supported_version_code' => 'integer',
        'rollout_permille' => 'integer',
    ];

    /** Restrict to one OTA channel. */
    public function scopeChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * The single build currently offered to a channel's fleet: the highest
     * version_code among that channel's published releases. Query with ->first().
     */
    public function scopeLiveManifest(Builder $query, string $channel): Builder
    {
        return $query->channel($channel)
            ->where('status', self::STATUS_PUBLISHED)
            ->orderByDesc('version_code');
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
