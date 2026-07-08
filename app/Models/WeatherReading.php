<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One rainfall reading: one station, one timestamp, one value. Written by
 * App\Services\Weather\WeatherIngestionService via insertOrIgnore, so the
 * (weather_station_id, observed_at) unique key makes re-syncs a no-op.
 *
 * Only created_at is tracked (readings are immutable once stored).
 */
class WeatherReading extends Model
{
    protected $table = 'weather_readings';

    public $timestamps = false;

    protected $fillable = [
        'weather_station_id',
        'provider',
        'observed_at',
        'value',
        'reading_type',
        'unit',
        'created_at',
    ];

    protected $casts = [
        'observed_at' => 'datetime',
        'value'       => 'float',
        'created_at'  => 'datetime',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(WeatherStation::class, 'weather_station_id');
    }
}
