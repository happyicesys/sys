<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Models\VendFan;
use App\Models\VendTemp;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteVendTemp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:vend-temp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete vending machine temperature';

    /**
     * Rows deleted per batch. Keeps each DELETE small so the job never holds a
     * large transaction/lock or stalls replication.
     */
    private const CHUNK_SIZE = 5000;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Retain ~14 days of readings. Compare on the raw `created_at` column
        // (NOT whereDate()/DATE(created_at)) so MySQL can use the created_at
        // index instead of full-scanning the whole table.
        $cutoff = Carbon::today()->subDays(14);

        $vendTempDeleted = $this->pruneOlderThan(VendTemp::class, $cutoff);
        $vendFanDeleted  = $this->pruneOlderThan(VendFan::class, $cutoff);

        $this->info("Pruned {$vendTempDeleted} vend_temps and {$vendFanDeleted} vend_fans older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }

    /**
     * Delete rows older than $cutoff in small, index-friendly batches.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function pruneOlderThan(string $model, Carbon $cutoff): int
    {
        $total = 0;

        do {
            $deleted = $model::where('created_at', '<', $cutoff)
                ->limit(self::CHUNK_SIZE)
                ->delete();

            $total += $deleted;

            if ($deleted > 0) {
                usleep(50_000); // 50ms breather between batches
            }
        } while ($deleted > 0);

        return $total;
    }
}
