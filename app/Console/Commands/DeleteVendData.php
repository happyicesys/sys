<?php

namespace App\Console\Commands;

use App\Models\VendData;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteVendData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:vend-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete vending machine log data older than 7 days (chunked).';

    /**
     * Retention window in days. Rows older than this (and is_keep=false) are deleted.
     */
    protected int $retentionDays = 7;

    /**
     * Max rows to delete per batch. Small enough to keep locks short, large enough
     * to drain a day's worth of logs in reasonable time.
     */
    protected int $chunkSize = 1000;

    /**
     * Microseconds to sleep between batches — small breathing room for the DB.
     */
    protected int $sleepBetweenChunksUs = 50000; // 50ms

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Use a single absolute cutoff (start of day - retentionDays). This is
        // index-friendly: `where('created_at', '<', $cutoff)` can use the
        // existing created_at index, whereas `whereDate(...)` wraps the column
        // in DATE() and can defeat the index on a timestamp column.
        $cutoff = Carbon::today()->subDays($this->retentionDays);

        $this->info("Deleting vend_data rows older than {$cutoff->toDateTimeString()} (retention={$this->retentionDays}d)");

        $totalDeleted = 0;
        $batches = 0;

        while (true) {
            $ids = VendData::where('created_at', '<', $cutoff)
                ->where('is_keep', false)
                ->orderBy('id')
                ->limit($this->chunkSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted = VendData::whereIn('id', $ids)->delete();

            $totalDeleted += $deleted;
            $batches++;

            usleep($this->sleepBetweenChunksUs);
        }

        $this->info("Done. Deleted {$totalDeleted} rows in {$batches} batch(es).");

        return self::SUCCESS;
    }
}
