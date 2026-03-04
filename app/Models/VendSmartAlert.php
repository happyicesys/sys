<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendSmartAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'vend_id',
        'alert_type',
        'severity',
        'meta_data',
        'is_active',
        'is_email_alert_sent',
        'email_alert_sent_at',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'is_active' => 'boolean',
        'is_email_alert_sent' => 'boolean',
        'email_alert_sent_at' => 'datetime',
    ];

    // Constants for Alert Types
    const TYPE_RISING_T1 = 'rising_t1_trend';
    const TYPE_RISING_T2 = 'rising_t2_trend';
    const TYPE_T2_FROZEN = 't2_frozen';

    // 2.1 Operation Error
    const TYPE_T1_HIGHER_THAN_T2 = 't1_higher_than_t2';
    const TYPE_COMP_FAN_OFF = 'comp_fan_off';
    const TYPE_TEMPS_ABOVE_0 = 'temps_above_0';
    const TYPE_TEMPS_ABOVE_MINUS_8 = 'temps_above_minus_8';
    const TYPE_NOT_REACH_MINUS_18 = 'not_reach_minus_18';
    const TYPE_TEMPS_ABOVE_MINUS_17_UPWARD = 'temps_above_minus_17_upward';

    // 2.2 Preventive
    const TYPE_LOWEST_24H_ABOVE = 'lowest_24h_above';
    const TYPE_LOWEST_72H_ABOVE = 'lowest_72h_above';

    // Relationships
    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
