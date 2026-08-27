<?php

namespace App\Services\SimcardUsage;

use App\Contracts\SimcardUsage\SimcardUsageProvider;
use InvalidArgumentException;

/**
 * Resolves a SimcardUsageProvider from config/simcard_usage.php by key (the
 * value stored in telcos.usage_provider). Adding a telco API is config-only:
 * register a class + endpoint under 'providers' and set usage_provider on the
 * telco row — nothing changes here or in the sync layer.
 */
class SimcardUsageProviderFactory
{
    public function make(string $key): SimcardUsageProvider
    {
        $providers = (array) config('simcard_usage.providers', []);

        if (! isset($providers[$key]) || empty($providers[$key]['class'])) {
            throw new InvalidArgumentException("Unknown or misconfigured simcard usage provider [{$key}].");
        }

        $config = $providers[$key];
        $class = $config['class'];

        $provider = new $class($config);

        if (! $provider instanceof SimcardUsageProvider) {
            throw new InvalidArgumentException("Provider [{$key}] must implement SimcardUsageProvider.");
        }

        return $provider;
    }
}
