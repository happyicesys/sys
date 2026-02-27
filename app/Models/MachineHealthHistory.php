<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MachineHealthHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'vend_id',
        'event',
        'alert_type',
        'bucket',
        'severity',
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

    public static function log($vendId, $event, $alertType, $bucket, $severity = null, $context = [], $occurredAt = null)
    {
        return self::create([
            'vend_id' => $vendId,
            'event' => $event,
            'alert_type' => $alertType,
            'bucket' => $bucket,
            'severity' => $severity,
            'context' => $context,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }
}
