<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reorder the per-minute freeze-scan index on ops_job_items.
 *
 * The original index (2026_05_31) led with `status`:
 *     ops_job_items_freeze_scan_idx (status, frozen_at, completed_at)
 *
 * The ops:freeze-stock-in query filters `status >= 3 AND status <> 99`, i.e. a
 * RANGE on the leading column. Once MySQL hits a range on the first index
 * column it can no longer seek on the columns after it, so `frozen_at` and
 * `completed_at` were reduced to row-by-row filters. Because almost every
 * historical row is status >= 3, the every-minute scan degraded into a
 * near-full index scan (~13.7s observed).
 *
 * Leading with `frozen_at` (IS NULL -> index ref) then `completed_at` (the sole
 * range, the 2-day window) lets the planner dive straight to "unfrozen items
 * completed in the window", with `status` checked in-index as a residual
 * filter. Identical result set, milliseconds instead of seconds.
 *
 * InnoDB builds/drops secondary indexes ONLINE (ALGORITHM=INPLACE), so this is
 * non-blocking to concurrent reads/writes, but still schedule it deliberately.
 */
return new class extends Migration
{
    private string $table = 'ops_job_items';
    private string $index = 'ops_job_items_freeze_scan_idx';

    public function up(): void
    {
        if ($this->indexExists($this->index)) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropIndex('ops_job_items_freeze_scan_idx');
            });
        }

        Schema::table($this->table, function (Blueprint $table) {
            $table->index(['frozen_at', 'completed_at', 'status'], 'ops_job_items_freeze_scan_idx');
        });
    }

    public function down(): void
    {
        if ($this->indexExists($this->index)) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropIndex('ops_job_items_freeze_scan_idx');
            });
        }

        // Restore the original (status, frozen_at, completed_at) column order.
        Schema::table($this->table, function (Blueprint $table) {
            $table->index(['status', 'frozen_at', 'completed_at'], 'ops_job_items_freeze_scan_idx');
        });
    }

    private function indexExists(string $name): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$this->table}"))
            ->contains(fn ($row) => $row->Key_name === $name);
    }
};
