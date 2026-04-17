<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add covering index on ops_job_items(ops_job_id, vend_id).
     *
     * OpsJobController::edit() builds a list of vends eligible to be added
     * to the job (the "unbinded vend options" dropdown). Previously it used:
     *
     *   whereDoesntHave('opsJobItems', fn($q) => $q->where('ops_job_id', ?))
     *
     * which generated a correlated NOT EXISTS subquery evaluated per-vend with
     * no index on vend_id — effectively a full table scan of ops_job_items for
     * every row in the vends table → 1700ms.
     *
     * The query is now rewritten as two steps:
     *   1. SELECT vend_id FROM ops_job_items WHERE ops_job_id = ?
     *   2. SELECT ... FROM vends WHERE id NOT IN (step-1 results)
     *
     * This index covers step 1 entirely from the index leaf nodes:
     *   - ops_job_id = constant  → seeks to the exact ops_job partition
     *   - vend_id               → read from leaf without heap access
     *
     * The result set is small (one ops_job typically has ~50–200 items),
     * making the subsequent NOT IN on the vends PK near-instant.
     */
    public function up(): void
    {
        if (!Schema::hasIndex('ops_job_items', 'idx_oji_job_vend')) {
            Schema::table('ops_job_items', function (Blueprint $table) {
                $table->index(['ops_job_id', 'vend_id'], 'idx_oji_job_vend');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('ops_job_items', 'idx_oji_job_vend')) {
            Schema::table('ops_job_items', function (Blueprint $table) {
                $table->dropIndex('idx_oji_job_vend');
            });
        }
    }
};
