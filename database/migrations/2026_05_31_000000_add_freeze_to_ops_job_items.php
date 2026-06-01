<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Freeze 10 min after stock-in" for ops job items.
 *
 * Each machine row in an ops job currently re-derives its displayed figures
 * (cash, counts, tally/error badge, coin float, etc.) live on every page load,
 * so the row keeps changing after the driver has finished (CMS/VMC/telemetry
 * keep syncing in). We want each item to FREEZE 10 minutes after its own
 * stock-in (status reaches STATUS_DELIVERED = 3).
 *
 *   frozen_at NULL  → item is live (re-derived on read)
 *   frozen_at set   → item is frozen to its stored snapshot (frozen_snapshot)
 *
 * A per-minute command (ops:freeze-stock-in) writes the snapshot once the
 * 10-minute window has elapsed. Undoing the stock-in (status drops below 3)
 * clears both columns and the row goes live again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            if (!Schema::hasColumn('ops_job_items', 'frozen_at')) {
                $table->timestamp('frozen_at')->nullable();
            }
            if (!Schema::hasColumn('ops_job_items', 'frozen_snapshot')) {
                $table->json('frozen_snapshot')->nullable();
            }
        });

        // Composite index keeps the every-minute freeze scan cheap:
        //   WHERE status >= 3 AND status <> 99 AND frozen_at IS NULL AND completed_at <= ?
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->index(['status', 'frozen_at', 'completed_at'], 'ops_job_items_freeze_scan_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->dropIndex('ops_job_items_freeze_scan_idx');
        });

        Schema::table('ops_job_items', function (Blueprint $table) {
            foreach (['frozen_at', 'frozen_snapshot'] as $col) {
                if (Schema::hasColumn('ops_job_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
