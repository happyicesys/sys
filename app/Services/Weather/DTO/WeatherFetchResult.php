<?php

namespace App\Services\Weather\DTO;

/**
 * The normalized result of one provider fetch: the station roster plus the
 * readings for the returned snapshot, tagged with the reading type and unit.
 */
class WeatherFetchResult
{
    /**
     * @param  WeatherStationData[]  $stations
     * @param  WeatherReadingData[]  $readings
     */
    public function __construct(
        public readonly array $stations,
        public readonly array $readings,
        public readonly string $readingType,
        public readonly string $unit,
    ) {
    }
}
