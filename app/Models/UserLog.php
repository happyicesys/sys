<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable audit row. Written only by App\Services\UserLogger via a raw
 * insert (so it never re-triggers Eloquent events). Read-only everywhere else.
 */
class UserLog extends Model
{
    /** No updated_at — rows are never mutated after creation. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'user_name', 'event',
        'auditable_type', 'auditable_id',
        'changes', 'source', 'ip', 'url',
    ];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
