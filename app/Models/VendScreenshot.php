<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One Setting/Edit "View Screen" capture, private on the default disk (DO Spaces in prod) under
 * `vend-screenshots/`. Written and pruned only by VendScreenshotHistory (newest five per machine).
 */
class VendScreenshot extends Model
{
    protected $fillable = [
        'vend_id',
        'requested_by',
        'path',
        'mime',
        'bytes',
        'width',
        'height',
        'captured_at',
    ];

    protected $casts = [
        'bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'captured_at' => 'datetime',
    ];

    public function vend()
    {
        return $this->belongsTo(Vend::class)->withoutGlobalScopes();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
