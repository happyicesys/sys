<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perf round 2026-06-12 (see PERFORMANCE_OPTIMIZATION_2026-06-12.md).
 *
 * product_movements previously only had FK indexes (product_id, operator_id,
 * user_id). The movement listing filters operator_id IN (...) + created_at
 * range, and incoming history / batch detail filter by batch_number — all
 * full scans today.
 *
 * Indexes change NOTHING about query results — read-only speed-up.
 * ADD INDEX on MySQL 8 is online DDL (INPLACE, no table copy).
 *
 * Idempotent: each index is only added if it doesn't already exist, so this
 * is safe to run even if the SQL from the plan doc was applied manually.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!$this->indexExists('product_movements', 'idx_pm_operator_created')) {
            Schema::table('product_movements', function (Blueprint $table) {
                $table->index(['operator_id', 'created_at'], 'idx_pm_operator_created');
            });
        }

        if (!$this->indexExists('product_movements', 'idx_pm_batch_number')) {
            Schema::table('product_movements', function (Blueprint $table) {
                $table->index('batch_number', 'idx_pm_batch_number');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('product_movements', 'idx_pm_operator_created')) {
            Schema::table('product_movements', function (Blueprint $table) {
                $table->dropIndex('idx_pm_operator_created');
            });
        }

        if ($this->indexExists('product_movements', 'idx_pm_batch_number')) {
            Schema::table('product_movements', function (Blueprint $table) {
                $table->dropIndex('idx_pm_batch_number');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return !empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]));
    }
};
