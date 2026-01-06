<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyWeatherHistory extends Model
{
    protected $fillable = [
        'date',
        'latitude',
        'longitude',
        'weather_code',
        'icon_code',
    ];
}
