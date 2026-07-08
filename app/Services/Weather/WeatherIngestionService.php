<?php

namespace App\Services\Weather;

use App\Contracts\Weather\WeatherProvider;
use App\Models\WeatherReading;
use App\Models\WeatherStation;
use App\Services\Weather\DTO\WeatherFetchResult;

/**
 * Persists a provider snapshot. Shared across every region — the provider does
 * the region-specific normalization, this just stores DTOs.
 *
 * Idempotent by design:
 *   - stations upserted on (provider, code);
 *   - readings written with insertOrIgnore against the
 *     (weather_station_id, observed_at) unique key, so re-pulling the same
 *     5-minute block (or overlapping cron runs) inserts nothing new.
 */
class WeatherIngestionService
{
    /**
     * @return array{stations:int, readings_total:int, readings_inserted:int, readings_skipped:int}
     */
    public function ingest(WeatherProvider $provider, WeatherFetchResult $result): array
    {
        $providerKey = $provider->key();
        $now = now();

        // 1. Upsert station metadata; build code => id map.
        $stationRows = [];
        foreach ($result->stations as $station) {
            $stationRows[] = [
                'provider'     => $providerKey,
                'region'       => $provider->region(),
                'code'         => $station->code,
                'name'         => $station->name,
                'latitude'     => $station->latitude,
                'longitude'    => $station->longitude,
                'unit'         => $result->unit,
                'last_seen_at' => $now,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        if ($stationRows !== []) {
            WeatherStation::upsert(
                $stationRows,
                ['provider', 'code'],
                ['name', 'latitude', 'longitude', 'unit', 'last_seen_at', 'updated_at']
            );
        }

        $stationIdByCode = WeatherStation::where('provider', $providerKey)
            ->pluck('id', 'code');

        // 2. Insert readings idempotently (skip any reading with no known station).
        $readingRows = [];
        foreach ($result->readings as $reading) {
            $stationId = $stationIdByCode[$reading->stationCode] ?? null;
            if ($stationId === null) {
                continue;
            }
            $readingRows[] = [
                'weather_station_id' => $stationId,
                'provider'           => $providerKey,
                'observed_at'        => $reading->observedAt,
                'value'              => $reading->value,
                'reading_type'       => $result->readingType,
                'unit'               => $result->unit,
                'created_at'         => $now,
            ];
        }

        $inserted = 0;
        foreach (array_chunk($readingRows, 500) as $chunk) {
            $inserted += WeatherReading::insertOrIgnore($chunk);
        }

        return [
            'stations'          => count($stationRows),
            'readings_total'    => count($readingRows),
            'readings_inserted' => $inserted,
            'readings_skipped'  => count($readingRows) - $inserted,
        ];
    }
}
