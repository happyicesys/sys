<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Our own recording of one freezer door session from one camera (camera 5 today), private on DO
 * Spaces under `freezer-videos/`. Written only by FreezerClipStore. NOT a Zijia video: nothing here
 * is ever sent to their AI.
 */
class SmartFreezerClip extends Model
{
    protected $fillable = [
        'vend_id',
        'session_ref',
        'camera_id',
        'path',
        'bytes',
        'duration_ms',
        'recorded_from',
        'door_closed_at',
        'meta',
    ];

    protected $casts = [
        'camera_id' => 'integer',
        'bytes' => 'integer',
        'duration_ms' => 'integer',
        'recorded_from' => 'datetime',
        'door_closed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function vend()
    {
        return $this->belongsTo(Vend::class)->withoutGlobalScopes();
    }

    /** Link + facts for a grid cell; the URL is the gated viewer route, never a Spaces URL. */
    public function toLink(): array
    {
        return [
            'id' => $this->id,
            'camera_id' => $this->camera_id,
            'seconds' => $this->duration_ms !== null ? (int) round($this->duration_ms / 1000) : null,
            'bytes' => $this->bytes,
            'url' => route('freezer-clips.show', $this->id),
        ];
    }
}
