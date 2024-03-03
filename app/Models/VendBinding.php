<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendBinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'begin_date',
        'termination_date',
        'is_active',
        'customer_id',
        'snap_parameter_json',
        'snap_vend_channels_json',
        'snap_vend_channel_error_logs_json',
        'snap_vend_status_json',
        'totals_json',
        'vend_id',
    ];
    // 'snap_vend_status_json' => [
    //     'coin_count', 'is_door_open', 'is_mqtt', 'is_mqtt_active', 'mqtt_last_updated_at', 'is_online', 'is_sensor', 'fan_speed', 'is_temp_error', 'last_updated_at', 't1', 'temp_updated_at', 't2', 't3', 't4', 'firmware_ver', 'apk_ver', 'apk_ver_build_time', 'location_type_name','account_manager_name',
    // ],
    protected $casts = [
        'snap_parameter_json' => 'json',
        'snap_vend_channels_json' => 'json',
        'snap_vend_channel_error_logs_json' => 'json',
        'snap_vend_status_json' => 'json',
        'totals_json' => 'json',
    ];

    // relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
