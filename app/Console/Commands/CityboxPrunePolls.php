<?php

namespace App\Console\Commands;

use App\Models\CityboxInventoryPoll;
use Illuminate\Console\Command;

/**
 * Nightly retention for the per-poll snapshot rows (~4,800/day for 10 units).
 * Movements (the ledger) are NEVER pruned — they are the truth. Chunked
 * deletes so it never holds a long lock on a busy table.
 */
class CityboxPrunePolls extends Command
{
    protected $signature = 'citybox:prune-polls {--days=90}';

    protected $description = 'Delete citybox_inventory_polls older than N days (movements are kept forever)';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $total = 0;
        do {
            $deleted = CityboxInventoryPoll::where('polled_at', '<', $cutoff)->limit(5000)->delete();
            $total += $deleted;
        } while ($deleted > 0);

        $this->info("Pruned {$total} poll rows older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
