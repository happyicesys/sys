<?php

namespace App\Console\Commands;

use App\Models\DeliveryPlatformMenuRecord;
use Illuminate\Console\Command;

/**
 * delivery_platform_menu_records holds every menu-sync callback Grab sends (QUEUEING /
 * PROCESSING / SUCCESS / FAILED per job) and was never pruned: 2.8M rows on
 * 2026-10-29. Deletes by id below the cutoff (no created_at index), in chunks.
 */
class PruneGrabMenuRecords extends Command
{
    protected $signature = 'grab:prune-menu-records
                            {--days= : Keep this many days (default config grab.menu_records_keep_days)}
                            {--chunk=5000 : Rows per DELETE}
                            {--apply : Delete; without it the command only counts}';

    protected $description = 'Delete Grab menu-sync callback records older than N days';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('grab.menu_records_keep_days'));

        if ($days < 1) {
            $this->error('Give --days or set GRAB_MENU_RECORDS_KEEP_DAYS.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $firstKeptId = DeliveryPlatformMenuRecord::query()->where('created_at', '>=', $cutoff)->min('id');
        $lastDeletedId = $firstKeptId
            ? $firstKeptId - 1
            : DeliveryPlatformMenuRecord::query()->max('id');

        $count = $lastDeletedId ? DeliveryPlatformMenuRecord::query()->where('id', '<=', $lastDeletedId)->count() : 0;

        $this->line(($this->option('apply') ? 'Deleting ' : 'DRY RUN - would delete ')."{$count} record(s) older than {$cutoff->toDateTimeString()}.");

        if (! $this->option('apply') || $count === 0) {
            return self::SUCCESS;
        }

        $chunk = max(100, (int) $this->option('chunk'));
        $deleted = 0;

        do {
            $batch = DeliveryPlatformMenuRecord::query()
                ->where('id', '<=', $lastDeletedId)
                ->orderBy('id')
                ->limit($chunk)
                ->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Deleted {$deleted}.");

        return self::SUCCESS;
    }
}
