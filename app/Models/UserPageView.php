<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user page-view tracker for the unread-note badges. See the
 * create_user_page_views_table migration for the meaning of last_viewed_at vs
 * unread_since. Read/written exclusively through NoteNotificationService.
 */
class UserPageView extends Model
{
    protected $fillable = [
        'user_id',
        'page_key',
        'last_viewed_at',
        'unread_since',
    ];

    protected $casts = [
        'last_viewed_at' => 'datetime',
        'unread_since' => 'datetime',
    ];
}
