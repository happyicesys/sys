<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendLog extends Model
{
    use HasFactory;
    public const EVENT_CHANNEL_ERROR = 'channel_error';
    public const EVENT_POWER_OFF = 'power_off';
    public const EVENT_POWER_RESTORED = 'power_restored';
    public const EVENT_NO_TRANSACTION = 'no_transaction';
    public const EVENT_ERROR = 'error';

    protected $fillable = [
        'vend_id',
        'event',
        'subject',
        'context',
        'occurred_at',
    ];

    protected $casts = [
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
