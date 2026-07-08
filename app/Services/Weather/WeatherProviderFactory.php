<?php

namespace App\Services\Weather;

use App\Contracts\Weather\WeatherProvider;
use InvalidArgumentException;

/**
 * Resolves a WeatherProvider from config/weather.php by key. Adding a region is
 * config-only: register a class + endpoint under 'providers', nothing else here
 * or in the ingestion layer changes.
 */
class WeatherProviderFactory
{
    public function make(?string $key = null): WeatherProvider
    {
        $key = $key ?: (string) config('weather.default');
        $providers = (array) config('weather.providers', []);

        if (! isset($providers[$key]) || empty($providers[$key]['class'])) {
            throw new InvalidArgumentException("Unknown or misconfigured weather provider [{$key}].");
        }

        $config = $providers[$key];
        $class = $config['class'];

        $provider = new $class($config);

        if (! $provider instanceof WeatherProvider) {
            throw new InvalidArgumentException("Provider [{$key}] must implement WeatherProvider.");
        }

        return $provider;
    }
}
