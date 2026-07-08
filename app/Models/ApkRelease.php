<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ApkRelease — one uploaded, signed Smart-Freezer APK build offered to the fleet
 * via OTA. See migration create_apk_releases_table for the field↔UpdateManifest map.
 *
 * The "live" manifest is always the highest version_code row with status = published
 * (see scopeLiveManifest). Ramping a rollout = editing rollout_permille; rolling back
 * = publishing a higher version_code that carries the previous good code (Android
 * refuses downgrades, so rollback is always "roll forward").
 */
class ApkRelease extends Model
{
    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
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

    /**
     * The single build currently offered to the fleet: the highest version_code
     * among published releases. Query with ->first().
     */
    public function scopeLiveManifest($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->orderByDesc('version_code');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
