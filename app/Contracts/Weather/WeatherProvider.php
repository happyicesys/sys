<?php

namespace App\Contracts\Weather;

use App\Services\Weather\DTO\WeatherFetchResult;

/**
 * A regional weather source. Each region/app implements this once and registers
 * itself in config/weather.php; the ingestion + storage layer is shared, so a
 * new region only needs to normalize its API into the provider-agnostic DTOs.
 */
interface WeatherProvider
{
    /** Stable short key stored on every row and used in config, e.g. "sg". */
    public function key(): string;

    /** Human-readable region label, e.g. "Singapore". */
    public function region(): string;

    /**
     * Fetch the latest rainfall snapshot and normalize it to shared DTOs.
     *
     * @throws \RuntimeException on transport or payload errors.
     */
    public function fetchRainfall(): WeatherFetchResult;
}
