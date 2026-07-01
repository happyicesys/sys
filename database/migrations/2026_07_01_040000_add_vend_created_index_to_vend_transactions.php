<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add (vend_id, created_at) to vend_transactions.
 *
 * OpsJobController computes each stock-in's "sales since the previous stock-in"
 * for a machine by summing vend_transactions WHERE vend_id = ? AND created_at
 * BETWEEN previous.completed_at AND this.completed_at. Every existing index on
 * vend_transactions is keyed on transaction_datetime (machine clock), NOT
 * created_at (server insert time) — and this query MUST use created_at, because
 * it is compared against completed_at, which is also server time.
 *
 * With no (vend_id, created_at) index, MySQL seeks vend_id via a
 * (vend_id, transaction_datetime) index and then scans that machine's ENTIRE
 * history, applying the created_at range + the per-row JSON GET_TYPE extraction
 * as residual filters — ~30s on a long-lived machine, and it runs on every
 * ops-job-item completion.
 *
 * (vend_id, created_at) lets it range-seek just the days between the two
 * stock-ins (a few hundred rows), applying settlement_status / the JSON OR to
 * that tiny set. created_at and vend_id are both write-once at insert (no
 * churn), so on this high-insert table the index only adds a single slim entry
 * per insert — a worthwhile trade against a 30s query. InnoDB builds it online
 * (non-blocking) but it's a large table, so run it in a quiet window.
 */
return new class extends Migration
{
    private string $table = 'vend_transactions';
    private string $index = 'idx_vtrans_vend_created';

    public function up(): void
    {
        if (! $this->indexExists($this->index)) {
            DB::statement("ALTER TABLE {$this->table} ADD INDEX {$this->index} (vend_id, created_at)");
        }
    }

    public function down(): void
    {
        if ($this->indexExists($this->index)) {
            DB::statement("ALTER TABLE {$this->table} DROP INDEX {$this->index}");
        }
    }

    private function indexExists(string $name): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$this->table}"))
            ->contains(fn ($row) => $row->Key_name === $name);
    }
};
