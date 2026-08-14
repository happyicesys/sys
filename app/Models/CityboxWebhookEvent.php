<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityboxWebhookEvent extends Model
{
    public const TYPE_ORDER = 'order';

    public const TYPE_REFUND = 'refund';

    public const TYPE_CLOSE = 'close';

    protected $fillable = [
        'type',
        'event_key',
        'vend_id',
        'payload',
        'raw_data',
        'signature_variant',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'json',
        'processed_at' => 'datetime',
    ];

    public function vend(): BelongsTo
    {
        return $this->belongsTo(Vend::class);
    }

    /** Signature verified at ingest — only these may ever be projected downstream. */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('signature_variant');
    }
}
