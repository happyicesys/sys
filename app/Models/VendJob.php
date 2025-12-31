<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendJob extends Model
{
    const RETRY_TIMEOUT_MINUTES = 5;
    const MAX_RETRY_DURATION_MINUTES = 60;
    const MIN_APK_VERSION_FOR_RETRY = 214;
    const IS_TESTING = false;


    protected $guarded = [];

    protected $casts = [
        // 'payload' => 'array', // Converted to string to preserve exact formatting including MD5/padding
        'response_payload' => 'array',
        'is_returned' => 'boolean',
        'response_at' => 'datetime',
    ];

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
