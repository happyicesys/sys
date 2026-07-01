<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Composite (is_returned, created_at) for the per-minute vend:retry-jobs scan.
 *
 * RetryVendJobs selects WHERE is_returned = 0 AND created_at >= (now - retry
 * window) AND updated_at < (now - timeout) AND EXISTS(vends … apkver >= 214).
 * vend_jobs only had a single-column is_returned index (low cardinality), so
 * the scan pulled ALL unreturned jobs and ran the per-vend JSON apkver EXISTS
 * across the whole set. (is_returned, created_at) seeks straight to recent
 * unreturned jobs, minimising the rows the EXISTS runs on.
 *
 * NET-NEUTRAL on write cost: this composite leads with is_returned, so it fully
 * supersedes the old single-column is_returned index (dropped here) — any
 * is_returned-only query uses the composite equally. Both are maintained only
 * when is_returned flips (0->1 on ack); created_at is write-once, and the
 * frequent updated_at/retries_count updates don't touch either key column.
 */
return new class extends Migration
{
    private string $table = 'vend_jobs';
    private string $newIndex = 'idx_vend_jobs_returned_created';
    private string $oldIndex = 'vend_jobs_is_returned_index'; // Laravel default name

    public function up(): void
    {
        if (! $this->indexExists($this->newIndex)) {
            DB::statement("ALTER TABLE {$this->table} ADD INDEX {$this->newIndex} (is_returned, created_at)");
        }
        // Old single-column is_returned index is now a redundant prefix — drop it.
        if ($this->indexExists($this->oldIndex)) {
            DB::statement("ALTER TABLE {$this->table} DROP INDEX {$this->oldIndex}");
        }
    }

    public function down(): void
    {
        if (! $this->indexExists($this->oldIndex)) {
            DB::statement("ALTER TABLE {$this->table} ADD INDEX {$this->oldIndex} (is_returned)");
        }
        if ($this->indexExists($this->newIndex)) {
            DB::statement("ALTER TABLE {$this->table} DROP INDEX {$this->newIndex}");
        }
    }

    private function indexExists(string $name): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$this->table}"))
            ->contains(fn ($row) => $row->Key_name === $name);
    }
};
