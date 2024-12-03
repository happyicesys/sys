<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ApkSettingVend extends Pivot
{
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
