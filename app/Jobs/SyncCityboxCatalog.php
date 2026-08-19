<?php

namespace App\Jobs;

use App\Models\CityboxProductSyncLog;
use App\Services\Citybox\CatalogSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Hourly full-catalog mirror. Thin: resolve service, run, log the summary. */
class SyncCityboxCatalog implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3300;

    public function __construct(
        public readonly string $source = CityboxProductSyncLog::SOURCE_CATALOG_SCHEDULED,
        public readonly ?int $userId = null,
    ) {}

    public function handle(CatalogSyncService $sync): void
    {
        $log = $sync->syncCatalog($this->source, $this->userId);

        Log::info('Citybox catalog synced', [
            'source' => $this->source, 'fetched' => $log->fetched,
            'added' => $log->added, 'updated' => $log->updated, 'delisted' => $log->delisted,
        ]);
    }
}
