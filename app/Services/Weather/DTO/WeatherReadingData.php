<?php

namespace App\Services\Weather\DTO;

use Carbon\CarbonInterface;

/**
 * Provider-agnostic single reading: one station, one timestamp, one value.
 */
class WeatherReadingData
{
    public function __construct(
        public readonly string $stationCode,
        public readonly CarbonInterface $observedAt,
        public readonly float $value,
    ) {
    }
}
