<?php

namespace App\Console\Commands;

use App\Models\OpsJob;
use App\Models\OpsJobItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FreezeStockIn extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:freeze-stock-in';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Snapshot ("freeze") ops job items 10 minutes after their stock-in, so the row stops re-deriving from live data.';

    /**
     * Execute the console command.
     *
     * Runs every minute. Finds items that have been stocked-in (status reached
     * STATUS_DELIVERED, with a completed_at) for at least 10 minutes and are not
     * yet frozen, then stores a snapshot of their currently-displayed figures.
     *
     * Idempotent (frozen_at IS NULL guard) and self-healing — if the scheduler
     * is down for a while it simply catches everything up on the next tick. The
     * 2-day lower bound keeps the scan from ever sweeping historical rows.
     */
    public function handle()
    {
        $cutoff = now()->subMinutes(10);
        $lowerBound = now()->subDays(2);

        $frozen = 0;
        $failed = 0;

        OpsJobItem::query()
            ->with([
                'opsJobItemChannels',
                'vend:id,parameter_json,vend_channel_error_logs_json,product_mapping_id,upcoming_product_mapping_id',
                'vend.productMapping:id,name,upcoming_product_mapping_id',
                'vend.productMapping.upcomingProductMapping:id,name,remarks',
                'vend.upcomingProductMapping:id,name,remarks',
            ])
            ->where('status', '>=', OpsJob::STATUS_DELIVERED)
            ->where('status', '<>', OpsJob::STATUS_CANCELLED)
            ->whereNull('frozen_at')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $cutoff)
            ->where('completed_at', '>=', $lowerBound)
            ->chunkById(100, function ($items) use (&$frozen, &$failed) {
                foreach ($items as $item) {
                    try {
                        $snapshot = $item->buildFreezeSnapshot();

                        OpsJobItem::whereKey($item->id)->update([
                            'frozen_snapshot' => json_encode($snapshot),
                            'frozen_at' => now(),
                        ]);

                        $frozen++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('ops:freeze-stock-in failed for ops_job_item ' . $item->id . ': ' . $e->getMessage());
                    }
                }
            });

        $this->info("Froze {$frozen} ops job item(s)" . ($failed ? ", {$failed} failed (see log)." : '.'));

        return Command::SUCCESS;
    }
}
