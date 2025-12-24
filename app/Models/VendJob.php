<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendJob extends Model
{
    const RETRY_TIMEOUT_MINUTES = 5;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'response_payload' => 'array',
        'is_returned' => 'boolean',
        'response_at' => 'datetime',
    ];

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
