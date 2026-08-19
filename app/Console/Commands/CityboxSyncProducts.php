<?php

namespace App\Console\Commands;

use App\Jobs\SyncCityboxCatalog;
use Illuminate\Console\Command;

/** Scheduler entry point for the hourly catalog mirror. Silent no-op when disabled. */
class CityboxSyncProducts extends Command
{
    protected $signature = 'citybox:sync-products {--now : Run inline instead of queueing}';

    protected $description = 'Mirror the CityBox product catalog into citybox_products (hourly)';

    public function handle(): int
    {
        if (! config('citybox.openapi.enabled')) {
            return self::SUCCESS;
        }
        if ($this->option('now')) {
            $log = app(\App\Services\Citybox\CatalogSyncService::class)->syncCatalog();
            $this->info("Catalog synced: {$log->fetched} fetched — {$log->summaryLine()}.");
        } else {
            SyncCityboxCatalog::dispatch();
            $this->info('Citybox catalog sync dispatched.');
        }

        return self::SUCCESS;
    }
}
