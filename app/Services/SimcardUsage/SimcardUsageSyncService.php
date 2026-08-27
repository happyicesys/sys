<?php

namespace App\Services\SimcardUsage;

use App\Models\Simcard;
use App\Models\Telco;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls live usage from every telco API (telcos.usage_provider → provider key)
 * and snapshots it onto simcards.usage_*. Shared across providers: adding a
 * telco API touches config + one provider class, never this layer.
 *
 * Writes bypass Eloquent timestamps on purpose — the Index's "Updated By"
 * column reads simcards.updated_at as "when a human last edited this row", and
 * a 10-minute cron must not masquerade as that.
 */
class SimcardUsageSyncService
{
    public function __construct(
        protected SimcardUsageProviderFactory $factory,
    ) {}

    /**
     * @param  string|null  $onlyProvider  Limit to one provider key (CLI --provider).
     * @return array{providers:int, synced:int, missing:int, rate_limited:int, failed_chunks:int}
     */
    public function sync(?string $onlyProvider = null): array
    {
        $stats = ['providers' => 0, 'synced' => 0, 'missing' => 0, 'rate_limited' => 0, 'failed_chunks' => 0];

        $keys = Telco::whereNotNull('usage_provider')
            ->when($onlyProvider, fn ($query) => $query->where('usage_provider', $onlyProvider))
            ->distinct()
            ->pluck('usage_provider');

        foreach ($keys as $key) {
            $stats['providers']++;
            $provider = $this->factory->make($key);

            $simcards = Simcard::where('is_active', 1)
                ->whereHas('telco', fn ($query) => $query->where('usage_provider', $key))
                ->get(['id', 'code', 'usage_status', 'usage_active_at', 'usage_expire_at', 'usage_used_mb', 'usage_synced_at'])
                ->keyBy('code');

            foreach ($simcards->chunk($provider->maxPerRequest()) as $chunk) {
                try {
                    $results = $provider->fetch($chunk->keys()->all());
                } catch (RateLimitedException $e) {
                    // Back off until the next cron run instead of hammering the
                    // remaining chunks; the untouched rows keep their last snapshot.
                    Log::warning('simcards:sync-usage rate limited', [
                        'provider' => $key,
                        'sims_total' => $simcards->count(),
                        'error' => $e->getMessage(),
                    ]);
                    $stats['rate_limited']++;

                    continue 2;
                } catch (Throwable $e) {
                    Log::error('simcards:sync-usage chunk failed', [
                        'provider' => $key,
                        'sims' => $chunk->count(),
                        'error' => $e->getMessage(),
                    ]);
                    $stats['failed_chunks']++;

                    continue;
                }

                foreach ($chunk as $code => $simcard) {
                    $usage = $results[$code] ?? null;
                    if (! $usage) {
                        // Per-sim API error — keep the previous snapshot; a stale
                        // usage_synced_at is the tell that this sim stopped syncing.
                        $stats['missing']++;

                        continue;
                    }

                    $simcard->forceFill([
                        'usage_status' => $usage->status,
                        'usage_active_at' => $usage->activeAt,
                        'usage_expire_at' => $usage->expireAt,
                        'usage_used_mb' => $usage->usedMb,
                        'usage_synced_at' => now(),
                    ]);
                    $simcard->timestamps = false;
                    $simcard->save();
                    $stats['synced']++;
                }
            }
        }

        return $stats;
    }
}
