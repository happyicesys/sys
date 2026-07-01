<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox / work-queue for the "freeze 10 min after stock-in" job.
 *
 * Instead of the every-minute command scanning the whole ops_job_items history
 * (with a 2-day window + filesort), we keep a tiny table of *only the items
 * waiting to be frozen*. An OpsJobItem observer enqueues a row when an item
 * becomes freeze-eligible and removes it when it no longer is; the command
 * scans this table (WHERE eligible_at <= now) and deletes each row once frozen.
 * The scan set is then proportional to pending items, not to table history.
 *
 *   eligible_at = completed_at + 10 minutes  (the existing freeze delay)
 *
 * Backfill seeds the queue from the items the current command would already be
 * processing (eligible, unfrozen, completed within the last 2 days) so nothing
 * in flight is lost on cutover. The 2-day bound deliberately does NOT sweep
 * pre-freeze-feature historical rows — same guard the old command used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_job_item_freeze_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ops_job_item_id')->unique();
            $table->timestamp('eligible_at')->index();
            $table->timestamps();
        });

        // Backfill the currently in-flight set (mirrors the old command's filter,
        // incl. the 2-day lower bound). Single INSERT ... SELECT; INSERT IGNORE
        // is a no-op if the observer already inserted a row for the same item.
        $now = now()->toDateTimeString();
        $lowerBound = now()->subDays(2)->toDateTimeString();

        DB::statement(
            "INSERT IGNORE INTO ops_job_item_freeze_queue
                (ops_job_item_id, eligible_at, created_at, updated_at)
             SELECT id, DATE_ADD(completed_at, INTERVAL 10 MINUTE), ?, ?
             FROM ops_job_items
             WHERE status >= '3'      -- OpsJob::STATUS_DELIVERED
               AND status <> '99'     -- OpsJob::STATUS_CANCELLED
               AND frozen_at IS NULL
               AND completed_at IS NOT NULL
               AND completed_at >= ?",
            [$now, $now, $lowerBound]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_job_item_freeze_queue');
    }
};
