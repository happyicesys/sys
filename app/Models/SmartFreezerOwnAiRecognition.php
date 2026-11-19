<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Our own freezer AI's count of one door session, as one office Mac (`worker`) answered it over MQTT (Brian,
 * 2026-11-19). Written only by App\Services\SmartFreezer\OwnAi\OwnAiLink. `items` = product id => units out of the
 * cabinet, like Claude's and PersonCount. SHADOW ONLY: nothing reads it to charge, release, refund or move stock.
 */
class SmartFreezerOwnAiRecognition extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'items' => 'array',
        'answer' => 'array',
        'auto_accept' => 'boolean',
        'job_sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function recognition(): BelongsTo
    {
        return $this->belongsTo(SmartFreezerRecognition::class, 'smart_freezer_recognition_id');
    }
}
