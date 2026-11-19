<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stretch of the same answer from a remote card terminal's status check: online, offline, or the check
 * itself failed (`online` NULL). Written only by {@see \App\Services\CardTerminal\TerminalStatusLog}.
 */
class RemoteCardTerminalStatusSpan extends Model
{
    protected $fillable = [
        'remote_card_terminal_id',
        'card_terminal_unit_id',
        'vend_id',
        'online',
        'last_state',
        'error',
        'started_at',
        'last_seen_at',
        'checks',
    ];

    protected $casts = [
        'online' => 'boolean',
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'checks' => 'integer',
    ];

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(RemoteCardTerminal::class, 'remote_card_terminal_id');
    }

    /** online / offline / check_failed */
    public function kind(): string
    {
        return match ($this->online) {
            true => 'online',
            false => 'offline',
            default => 'check_failed',
        };
    }
}
