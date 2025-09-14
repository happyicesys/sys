<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertEmailItem extends Model
{
    protected $fillable = [
        'email',
        'is_active',
        'is_send_channel_error_log',
        'is_send_offline_notification',
        'is_send_power_restored_notification',
        'operator_id',
        'user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_send_channel_error_log' => 'boolean',
        'is_send_offline_notification' => 'boolean',
        'is_send_power_restored_notification' => 'boolean',
    ];

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
