<?php

namespace App\Console\Commands;

use App\Services\Weather\WeatherIngestionService;
use App\Services\Weather\WeatherProviderFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pull the latest rainfall snapshot from the configured regional provider and
 * store it. Scheduled every 5 minutes (matches the data.gov.sg refresh); safe
 * to overlap-guard and re-run since ingestion is idempotent.
 *
 *   php artisan weather:sync-rainfall              # default provider
 *   php artisan weather:sync-rainfall --provider=sg
 *   php artisan weather:sync-rainfall --dry-run    # fetch + report, write nothing
 */
class SyncWeatherRainfall extends Command
{
    protected $signature = 'weather:sync-rainfall
        {--provider= : Provider key (defaults to config weather.default).}
        {--dry-run : Fetch and report only; write nothing.}';

    protected $description = 'Ingest the latest rainfall readings from the regional weather provider.';

    public function handle(WeatherProviderFactory $factory, WeatherIngestionService $ingestion): int
    {
        try {
            $provider = $factory->make($this->option('provider') ?: null);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            $result = $provider->fetchRainfall();
        } catch (Throwable $e) {
            Log::error('weather:sync-rainfall fetch failed', [
                'provider' => $provider->key(),
                'error'    => $e->getMessage(),
            ]);
            $this->error("Fetch failed [{$provider->key()}]: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                '[%s] %d stations, %d readings (%s, %s). Dry-run: nothing written.',
                $provider->key(),
                count($result->stations),
                count($result->readings),
                $result->readingType,
                $result->unit,
            ));

            return self::SUCCESS;
        }

        $stats = $ingestion->ingest($provider, $result);

        $this->info(sprintf(
            '[%s] stations=%d readings_inserted=%d skipped=%d',
            $provider->key(),
            $stats['stations'],
            $stats['readings_inserted'],
            $stats['readings_skipped'],
        ));

        return self::SUCCESS;
    }
}
