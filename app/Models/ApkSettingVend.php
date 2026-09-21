<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ApkSettingVend extends Pivot
{
    /**
     * `apk_setting_vend` has an auto-increment id, but Pivot switches `incrementing` off, so
     * the id was never read back after an insert. The audit logger (UserLogger) then
     * had no record id to file the 'created' row under and lost it — the trail showed
     * machines being removed and never added (prod 2026-09-21).
     */
    public $incrementing = true;

    use HasFactory;

    protected $table = 'apk_setting_vend';

    protected $fillable = [
        'apk_setting_id',
        'vend_id',
    ];

    public function apkSetting()
    {
        return $this->belongsTo(ApkSetting::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
