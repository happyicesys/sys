<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of the remote-terminal timeline (see the migration). Append-only:
 * written by CardTerminalEventLog, never updated.
 */
class CardPaymentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'vend_id',
        'remote_card_terminal_id',
        'custom_order_id',
        'event',
        'level',
        'detail',
        'duration_ms',
        'created_at',
    ];

    protected $casts = [
        'detail' => 'array',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.v';
    }
}
