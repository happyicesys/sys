<?php

namespace App\Console\Commands;

use App\Models\OpsJobItem;
use App\Models\OpsJobItemFreezeQueue;
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
     * Runs every minute. Instead of scanning the whole ops_job_items table, it
     * consumes the freeze work-queue (ops_job_item_freeze_queue) — a small table
     * holding only the items waiting to freeze, maintained by OpsJobItemObserver.
     * For each queued row whose 10-minute delay has elapsed it stores the
     * snapshot and removes the row. The scan cost is proportional to pending
     * items, not to table history.
     *
     * Self-healing: a snapshot failure leaves the queue row in place so it is
     * retried next tick; the hourly ops:freeze-queue-reconcile sweep re-enqueues
     * anything an observer might have missed (e.g. a raw bulk status update).
     */
    public function handle()
    {
        $now = now();

        $frozen = 0;
        $failed = 0;
        $skipped = 0;

        OpsJobItemFreezeQueue::query()
            ->where('eligible_at', '<=', $now)
            ->with([
                'opsJobItem.opsJobItemChannels',
                'opsJobItem.vend:id,parameter_json,vend_channel_error_logs_json,product_mapping_id,upcoming_product_mapping_id',
                'opsJobItem.vend.productMapping:id,name,upcoming_product_mapping_id',
                'opsJobItem.vend.productMapping.upcomingProductMapping:id,name,remarks',
                'opsJobItem.vend.upcomingProductMapping:id,name,remarks',
            ])
            ->chunkById(100, function ($queueRows) use (&$frozen, &$failed, &$skipped) {
                foreach ($queueRows as $queueRow) {
                    $item = $queueRow->opsJobItem;

                    // Item gone, or no longer eligible (undone / cancelled / already
                    // frozen after enqueue) — drop the stale row and move on.
                    if (!$item || !$item->isFreezeEligible()) {
                        $queueRow->delete();
                        $skipped++;
                        continue;
                    }

                    try {
                        $snapshot = $item->buildFreezeSnapshot();

                        OpsJobItem::whereKey($item->id)->update([
                            'frozen_snapshot' => json_encode($snapshot),
                            'frozen_at' => now(),
                        ]);

                        $queueRow->delete();
                        $frozen++;
                    } catch (\Throwable $e) {
                        // Leave the queue row in place so it retries next tick.
                        $failed++;
                        Log::error('ops:freeze-stock-in failed for ops_job_item ' . $item->id . ': ' . $e->getMessage());
                    }
                }
            });

        $this->info(
            "Froze {$frozen} ops job item(s)"
            . ($skipped ? ", {$skipped} stale queue row(s) cleared" : '')
            . ($failed ? ", {$failed} failed (see log)." : '.')
        );

        return Command::SUCCESS;
    }
}
