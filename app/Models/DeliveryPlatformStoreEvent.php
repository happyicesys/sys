<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only history of one Grab store (merchantID): binds, unbinds, pauses,
 * switch-offs. Written only by GrabStoreService. user_id null = the system.
 */
class DeliveryPlatformStoreEvent extends Model
{
    const UPDATED_AT = null;

    const EVENT_CREATED = 'created';

    const EVENT_BOUND = 'bound';

    const EVENT_UNBOUND = 'unbound';

    const EVENT_PAUSED = 'paused';

    const EVENT_RESUMED = 'resumed';

    const EVENT_SWITCHED_OFF = 'switched_off';

    const EVENT_SWITCHED_ON = 'switched_on';

    const EVENT_RENAMED = 'renamed';

    protected $fillable = [
        'data',
        'delivery_platform_ref_number_id',
        'delivery_product_mapping_vend_id',
        'event',
        'reason',
        'user_id',
        'vend_id',
    ];

    protected $casts = [
        'data' => 'json',
    ];

    public function deliveryPlatformRefNumber()
    {
        return $this->belongsTo(DeliveryPlatformRefNumber::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
