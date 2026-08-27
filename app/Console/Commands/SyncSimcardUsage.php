<?php

namespace App\Console\Commands;

use App\Services\SimcardUsage\SimcardUsageSyncService;
use Illuminate\Console\Command;

/**
 * Snapshot live sim status from every telco usage API (currently VoicePing)
 * onto simcards.usage_* for the Simcard Index "Status" column. Scheduled every
 * 10 minutes; idempotent, so overlap-guarded re-runs are safe. A rate-limited
 * provider logs a warning and simply waits for the next run.
 *
 *   php artisan simcards:sync-usage
 *   php artisan simcards:sync-usage --provider=voiceping
 */
class SyncSimcardUsage extends Command
{
    protected $signature = 'simcards:sync-usage
        {--provider= : Limit to one provider key (defaults to all mapped telcos).}';

    protected $description = 'Pull live sim usage/status from telco APIs onto simcards.usage_*.';

    public function handle(SimcardUsageSyncService $service): int
    {
        $stats = $service->sync($this->option('provider') ?: null);

        $this->info(sprintf(
            'providers=%d synced=%d missing=%d rate_limited=%d failed_chunks=%d',
            $stats['providers'],
            $stats['synced'],
            $stats['missing'],
            $stats['rate_limited'],
            $stats['failed_chunks'],
        ));

        return ($stats['rate_limited'] || $stats['failed_chunks']) ? self::FAILURE : self::SUCCESS;
    }
}
