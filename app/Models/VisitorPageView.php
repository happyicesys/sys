<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One page opened during a VisitorSession.
 *
 * duration_seconds is either 'beacon' (the browser told us exactly how long the
 * page was on screen, including the last page before the tab closed) or
 * 'inferred' (we only know the gap until the next page view). The distinction is
 * surfaced in the UI so nobody reads an inferred number as gospel.
 */
class VisitorPageView extends Model
{
    protected $fillable = [
        'visit_uuid',
        'visitor_session_id',
        'user_id',
        'path',
        'query_string',
        'route_name',
        'ip',
        'viewed_at',
        'left_at',
        'duration_seconds',
        'active_seconds',
        'duration_source',
    ];

    protected $casts = [
        'viewed_at'        => 'datetime',
        'left_at'          => 'datetime',
        'duration_seconds' => 'integer',
        'active_seconds'   => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function visitorSession(): BelongsTo
    {
        return $this->belongsTo(VisitorSession::class);
    }
}
