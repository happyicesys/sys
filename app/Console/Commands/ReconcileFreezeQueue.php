<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileFreezeQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:freeze-queue-reconcile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safety net: re-enqueue any freeze-eligible ops job items missing from the freeze work-queue (e.g. after a bulk/raw status update that bypassed the observer).';

    /**
     * Execute the console command.
     *
     * The observer keeps ops_job_item_freeze_queue in sync for normal Eloquent
     * saves. This hourly sweep covers the rare paths that bypass model events
     * (bulk query-builder updates). It only INSERTs missing rows — the
     * every-minute freeze command still does the actual freezing and removal.
     *
     * Mirrors the original scan filter, including the 2-day lower bound, so it
     * never sweeps pre-freeze-feature historical rows. INSERT IGNORE makes it a
     * no-op for items already queued.
     */
    public function handle()
    {
        $now = now()->toDateTimeString();
        $lowerBound = now()->subDays(2)->toDateTimeString();

        $before = DB::table('ops_job_item_freeze_queue')->count();

        DB::statement(
            "INSERT IGNORE INTO ops_job_item_freeze_queue
                (ops_job_item_id, eligible_at, created_at, updated_at)
             SELECT oji.id, DATE_ADD(oji.completed_at, INTERVAL 10 MINUTE), ?, ?
             FROM ops_job_items oji
             LEFT JOIN ops_job_item_freeze_queue q ON q.ops_job_item_id = oji.id
             WHERE q.id IS NULL
               AND oji.status >= '3'     -- OpsJob::STATUS_DELIVERED
               AND oji.status <> '99'    -- OpsJob::STATUS_CANCELLED
               AND oji.frozen_at IS NULL
               AND oji.completed_at IS NOT NULL
               AND oji.completed_at >= ?",
            [$now, $now, $lowerBound]
        );

        $added = DB::table('ops_job_item_freeze_queue')->count() - $before;

        $this->info("Reconcile: re-enqueued {$added} missing freeze-eligible item(s).");

        return Command::SUCCESS;
    }
}
