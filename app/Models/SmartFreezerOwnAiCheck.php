<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's check of HappyIce AI's count on one door session (Brian, 2026-11-22). `items` is the truth (product id =>
 * units out of the cabinet); `ai_items` what HappyIce AI said at the time. The office Macs train on it at 03:15.
 * SHADOW ONLY: nothing reads it to charge, release, refund or move stock.
 */
class SmartFreezerOwnAiCheck extends Model
{
    public const RIGHT = 'right';

    public const WRONG = 'wrong';

    protected $guarded = ['id'];

    protected $casts = [
        'items' => 'array',
        'ai_items' => 'array',
        'checked_at' => 'datetime',
    ];

    public function recognition(): BelongsTo
    {
        return $this->belongsTo(SmartFreezerRecognition::class, 'smart_freezer_recognition_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
