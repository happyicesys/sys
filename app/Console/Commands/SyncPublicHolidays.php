<?php

namespace App\Console\Commands;

use App\Services\Holiday\HolidayDayRebuildService;
use App\Services\Holiday\HolidayIngestionService;
use App\Services\Holiday\HolidayProviderFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sync official public holidays from the configured regional provider into the
 * `holidays` table. Scheduled monthly (data barely changes; monthly re-runs are
 * self-healing if one fails). Idempotent, so re-runs never duplicate.
 *
 *   php artisan holidays:sync-public              # default provider
 *   php artisan holidays:sync-public --provider=sg
 *   php artisan holidays:sync-public --dry-run    # fetch + report, write nothing
 */
class SyncPublicHolidays extends Command
{
    protected $signature = 'holidays:sync-public
        {--provider= : Provider key (defaults to config holiday.default).}
        {--dry-run : Fetch and report only; write nothing.}
        {--force : Write even if HOLIDAY_SYNC_ENABLED is off (for one-off manual runs).}';

    protected $description = 'Sync official public holidays from the regional provider into the holidays table.';

    public function handle(
        HolidayProviderFactory $factory,
        HolidayIngestionService $ingestion,
        HolidayDayRebuildService $rebuild
    ): int {
        try {
            $provider = $factory->make($this->option('provider') ?: null);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            $holidays = $provider->fetchPublicHolidays();
        } catch (Throwable $e) {
            Log::error('holidays:sync-public fetch failed', [
                'provider' => $provider->key(),
                'error'    => $e->getMessage(),
            ]);
            $this->error("Fetch failed [{$provider->key()}]: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                '[%s] %d public holidays fetched. Dry-run: nothing written.',
                $provider->key(),
                count($holidays),
            ));
            foreach ($holidays as $h) {
                $this->line("  {$h->date}  {$h->name}");
            }

            return self::SUCCESS;
        }

        // Storage gate: this deployment only persists holidays when it has opted
        // in via HOLIDAY_SYNC_ENABLED. Keeps the Indonesia app (flag off) from
        // ever storing SG holiday data, even on an accidental manual invocation.
        if (! (bool) config('holiday.sync_enabled') && ! $this->option('force')) {
            $this->warn(sprintf(
                '[%s] Holiday storage is disabled for this deployment (HOLIDAY_SYNC_ENABLED is off). '
                . 'Fetched %d holidays but wrote nothing. Use --force to override.',
                $provider->key(),
                count($holidays),
            ));

            return self::SUCCESS;
        }

        $stats = $ingestion->ingestPublic($provider, $holidays);

        // Refresh the derived daily table so downstream (MCP / analysis) joins
        // stay in sync with the source rows we just wrote.
        $dayStats = $rebuild->rebuild();

        $this->info(sprintf(
            '[%s] fetched=%d created=%d updated=%d holiday_days=%d',
            $provider->key(),
            $stats['total'],
            $stats['created'],
            $stats['updated'],
            $dayStats['dates'],
        ));

        return self::SUCCESS;
    }
}
