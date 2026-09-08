<?php

namespace App\Services\SimcardUsage;

use App\Contracts\SimcardUsage\SimcardUsageProvider;
use App\Models\Telco;
use InvalidArgumentException;

/**
 * Resolves a SimcardUsageProvider from config/simcard_usage.php by key (the
 * value stored in telcos.usage_provider). Adding a telco API is config-only:
 * register a class + endpoint under 'providers' and set usage_provider on the
 * telco row — nothing changes here or in the sync layer.
 *
 * A package may carry its own API query link (telcos.usage_endpoint); it is
 * layered over the provider's config so one provider class serves several
 * packages that live behind different URLs.
 */
class SimcardUsageProviderFactory
{
    /**
     * @param  array<string, mixed>  $overrides  Per-package config layered over the provider's defaults.
     */
    public function make(string $key, array $overrides = []): SimcardUsageProvider
    {
        $providers = (array) config('simcard_usage.providers', []);

        if (! isset($providers[$key]) || empty($providers[$key]['class'])) {
            throw new InvalidArgumentException("Unknown or misconfigured simcard usage provider [{$key}].");
        }

        $config = array_merge($providers[$key], array_filter($overrides, fn ($v) => $v !== null && $v !== ''));
        $class = $config['class'];

        $provider = new $class($config);

        if (! $provider instanceof SimcardUsageProvider) {
            throw new InvalidArgumentException("Provider [{$key}] must implement SimcardUsageProvider.");
        }

        return $provider;
    }

    /** The provider for one SimCard Package, honouring its usage_endpoint override. */
    public function makeForTelco(Telco $telco): SimcardUsageProvider
    {
        if (! $telco->usage_provider) {
            throw new InvalidArgumentException("Telco [{$telco->name}] has no usage_provider.");
        }

        return $this->make($telco->usage_provider, ['endpoint' => $telco->usage_endpoint]);
    }
}
