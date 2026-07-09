<?php

namespace App\Services\Holiday;

use App\Contracts\Holiday\HolidayProvider;
use InvalidArgumentException;

/**
 * Resolves a HolidayProvider from config/holiday.php by key. Adding a region is
 * config-only: register a class + endpoint under 'providers', nothing else here
 * or in the ingestion layer changes.
 */
class HolidayProviderFactory
{
    public function make(?string $key = null): HolidayProvider
    {
        $key = $key ?: (string) config('holiday.default');
        $providers = (array) config('holiday.providers', []);

        if (! isset($providers[$key]) || empty($providers[$key]['class'])) {
            throw new InvalidArgumentException("Unknown or misconfigured holiday provider [{$key}].");
        }

        $config = $providers[$key];
        $class = $config['class'];

        $provider = new $class($config);

        if (! $provider instanceof HolidayProvider) {
            throw new InvalidArgumentException("Provider [{$key}] must implement HolidayProvider.");
        }

        return $provider;
    }
}
