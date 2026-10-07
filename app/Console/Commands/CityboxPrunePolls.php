<?php

namespace App\Console\Commands;

use App\Models\CityboxInventoryPoll;
use Illuminate\Console\Command;

/**
 * Nightly retention for the per-poll snapshot rows (~10,000/day for 10 units,
 * ~4 KB each with the snapshot — ~40 MB/day). 30 days (Brian, 2026-10-07: the
 * table had reached 1.3 GB with the disk at 87%). Movements (the ledger) are
 * NEVER pruned — they are the truth, and nothing reads their poll_id back.
 * Chunked deletes so it never holds a long lock on a busy table.
 */
class CityboxPrunePolls extends Command
{
    protected $signature = 'citybox:prune-polls {--days=30}';

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
