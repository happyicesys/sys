<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A weather sensor (e.g. a data.gov.sg rainfall station). Metadata is upserted
 * on every sync by App\Services\Weather\WeatherIngestionService, keyed on
 * (provider, code). See the create migration for the full contract.
 */
class WeatherStation extends Model
{
    protected $table = 'weather_stations';

    protected $fillable = [
        'provider',
        'region',
        'code',
        'name',
        'latitude',
        'longitude',
        'unit',
        'last_seen_at',
    ];

    protected $casts = [
        'latitude'     => 'float',
        'longitude'    => 'float',
        'last_seen_at' => 'datetime',
    ];

    public function readings(): HasMany
    {
        return $this->hasMany(WeatherReading::class);
    }
}
