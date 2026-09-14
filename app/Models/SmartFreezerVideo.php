<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A camera-video push from the smart-freezer supplier (Zijia). Written only by
 * ZijiaVideoWebhookController; `raw_body` is the untouched request body, `payload` its parsed form.
 */
class SmartFreezerVideo extends Model
{
    protected $fillable = [
        'supplier',
        'vend_id',
        'order_no',
        'device_id',
        'video_urls',
        'payload',
        'raw_body',
        'content_type',
        'source_ip',
    ];

    protected $casts = [
        'video_urls' => 'array',
        'payload' => 'array',
    ];

    public function vend()
    {
        return $this->belongsTo(Vend::class)->withoutGlobalScopes();
    }
}
