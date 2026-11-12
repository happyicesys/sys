<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A paid freezer session that ended in an error, as the machine reported it. A record only: no sale,
 * no stock, no money. `items`: [{sku, name, unit_cents, qty, channel, product_id}]. `door_log` (app 57+):
 * {first_order_at_ms, error_shown_at_ms, steps: [{t_ms, kind, try, text}]} — every door try, the host's answers
 * and the rescue's reads, timed on the machine from the first door order (`FreezerDoorTimeline` reads it).
 */
class FreezerFailedSession extends Model
{
    public const METHOD_CARD = 'card';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'door_log' => 'array',
            'is_test' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function vend(): BelongsTo
    {
        return $this->belongsTo(Vend::class);
    }
}
