<?php

namespace App\Console\Commands;

use App\Models\GrabMenuSync;
use Illuminate\Console\Command;

// Grab Menu Sync keeps 90 days (its stores go with each row, cascade).
class PruneGrabMenuSyncs extends Command
{
    const KEEP_DAYS = 90;

    protected $signature = 'grab:prune-menu-syncs';

    protected $description = 'Delete Grab Menu Sync rows older than 90 days';

    public function handle(): int
    {
        $deleted = 0;
        do {
            $batch = GrabMenuSync::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS))->limit(500)->pluck('id');
            $deleted += $batch->isEmpty() ? 0 : GrabMenuSync::query()->whereIn('id', $batch)->delete();
        } while ($batch->isNotEmpty());

        $this->info("Deleted {$deleted} Grab Menu Sync row(s).");

        return self::SUCCESS;
    }
}
