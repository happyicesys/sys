<?php

namespace App\Services\Weather\DTO;

/**
 * Provider-agnostic station metadata (one sensor).
 */
class WeatherStationData
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
    ) {
    }
}
