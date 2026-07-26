<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One login session for the Admin > Visitor History log. Created by the Login
 * event listener (or lazily by LogVisitorActivity for users who were already
 * signed in when this feature shipped) and closed by the Logout listener or the
 * browser unload beacon.
 */
class VisitorSession extends Model
{
    protected $fillable = [
        'user_id',
        'ip',
        'user_agent',
        'device_type',
        'platform',
        'browser',
        'browser_version',
        'login_at',
        'last_activity_at',
        'ended_at',
        'end_reason',
        'page_view_count',
    ];

    protected $casts = [
        'login_at'         => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at'         => 'datetime',
        'page_view_count'  => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(VisitorPageView::class);
    }
}
