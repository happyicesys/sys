<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One store a Grab menu change reached, followed until Grab confirms it took the menu. Written only by
 * GrabMenuSyncTracker.
 */
class GrabMenuSyncStore extends Model
{
    // Queued: the notify waits its debounce (or Grab's lock) before asking Grab.
    const STATUS_WAITING = 'waiting';

    // Grab accepted "read the menu again" and has not pulled it yet.
    const STATUS_SENT = 'sent';

    // Grab pulled the menu; its own SUCCESS has not come yet.
    const STATUS_PULLED = 'pulled';

    // Grab refused the ask (too many requests, too frequently, an error); asked again at retry_at.
    const STATUS_TURNED_AWAY = 'turned_away';

    // Grab confirmed it took the menu (or already had these prices).
    const STATUS_SYNCED = 'synced';

    // Not on Grab: refused after every retry, Grab's job FAILED, or Grab never pulled.
    const STATUS_FAILED = 'failed';

    // Nothing was asked: the store does not sell on Grab now.
    const STATUS_SKIPPED = 'skipped';

    // Not asked of Grab yet, or asked and refused: the next notify job answers for these.
    const BEFORE_SEND = [self::STATUS_WAITING, self::STATUS_TURNED_AWAY];

    // Grab has the ask; its pull or its SUCCESS is awaited.
    const AWAITING_GRAB = [self::STATUS_SENT, self::STATUS_PULLED];

    const OPEN = [self::STATUS_WAITING, self::STATUS_TURNED_AWAY, self::STATUS_SENT, self::STATUS_PULLED];

    protected $fillable = [
        'grab_menu_sync_id', 'delivery_product_mapping_vend_id', 'delivery_platform_ref_number_id', 'operator_id',
        'merchant_id', 'vend_code', 'status', 'attempts', 'retry_at', 'last_code', 'last_message', 'sent_at',
        'pulled_at', 'synced_at', 'last_checked_at', 'expected_json',
    ];

    protected $casts = [
        'expected_json' => 'array',
        'retry_at' => 'datetime',
        'sent_at' => 'datetime',
        'pulled_at' => 'datetime',
        'synced_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function sync(): BelongsTo
    {
        return $this->belongsTo(GrabMenuSync::class, 'grab_menu_sync_id');
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(DeliveryProductMappingVend::class, 'delivery_product_mapping_vend_id')->withoutGlobalScopes();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
