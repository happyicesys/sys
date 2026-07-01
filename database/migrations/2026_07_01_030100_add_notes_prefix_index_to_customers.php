<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prefix index on customers.notes so `notes IS NOT NULL` can SEEK.
 *
 * The @-mention badge (NoteNotificationService) counts customers whose notes
 * @-mention the user via `notes LIKE '%@name%'` — a leading-wildcard LIKE that
 * can never use an index. Before the LIKE, the query filters `notes IS NOT NULL`.
 * Notes are a sparse, recent ops feature (most customers have none), so that
 * predicate is highly selective — but without an index MySQL still scans every
 * customer row and runs the LIKE/REGEXP on each.
 *
 * A prefix index lets MySQL range-scan `notes IS NOT NULL` to just the
 * note-bearing rows, then apply the LIKE/REGEXP to that small candidate set.
 * This speeds BOTH the bounded (post-visit) and the unbounded (never-visited,
 * $since = null) mention counts. `notes` is a TEXT column, so the index needs a
 * prefix length; 191 chars is the universally-safe utf8mb4 prefix.
 *
 * Behaviour-preserving: index only, no query/logic change (the optimizer chooses
 * it for IS NOT NULL). Touches only `customers`, which is not hot-write for
 * notes, so no other query path is affected.
 */
return new class extends Migration
{
    private string $index = 'idx_customers_notes_prefix';

    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'notes')) {
            return;
        }
        if ($this->indexExists()) {
            return;
        }

        // Laravel's schema builder can't express a prefix length; use raw SQL.
        DB::statement("ALTER TABLE customers ADD INDEX {$this->index} (notes(191))");
    }

    public function down(): void
    {
        if ($this->indexExists()) {
            DB::statement("ALTER TABLE customers DROP INDEX {$this->index}");
        }
    }

    private function indexExists(): bool
    {
        return collect(DB::select('SHOW INDEX FROM customers'))
            ->contains(fn ($row) => $row->Key_name === $this->index);
    }
};
