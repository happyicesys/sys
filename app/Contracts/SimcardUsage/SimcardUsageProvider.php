<?php

namespace App\Contracts\SimcardUsage;

/**
 * A telco usage API. Each telco with an API implements this once and registers
 * itself in config/simcard_usage.php; telcos.usage_provider on the telco row
 * names the key. The sync layer (SimcardUsageSyncService) is shared, so a new
 * telco only needs to normalize its API into SimcardUsageData.
 */
interface SimcardUsageProvider
{
    /** Stable short key used in config and telcos.usage_provider, e.g. "voiceping". */
    public function key(): string;

    /** Max sim numbers the API accepts per request; the sync layer chunks to this. */
    public function maxPerRequest(): int;

    /**
     * Fetch live usage for up to maxPerRequest() sim numbers, keyed by sim
     * number. Sims the API errored on individually are simply absent.
     *
     * @param  list<string>  $simNos
     * @return array<string, \App\Services\SimcardUsage\DTO\SimcardUsageData>
     *
     * @throws \App\Services\SimcardUsage\RateLimitedException on HTTP 429.
     * @throws \RuntimeException on transport or payload errors.
     */
    public function fetch(array $simNos): array;
}
