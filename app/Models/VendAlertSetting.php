<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendAlertSetting extends Model
{
    protected $fillable = [
        'vend_id',
        'offline_after_minutes',
        'power_restored_after_minutes',
        'no_sales_after_hours',
    ];

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
