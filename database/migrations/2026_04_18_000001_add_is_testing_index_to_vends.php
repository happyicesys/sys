<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a standalone index on vends.is_testing.
 *
 * The dashboard performance page runs:
 *   SELECT id FROM vends WHERE is_testing = 1          (DashboardController#72)
 *   SELECT id FROM vends WHERE is_testing = 1
 *     OR customer_id IS NULL                           (DashboardController#561)
 *
 * The existing idx_vends_operator_active_testing starts with operator_id, so it
 * cannot satisfy these queries (no operator_id filter is applied).
 * A dedicated is_testing index lets MySQL seek directly to the small set of
 * testing machines instead of scanning the entire vends table.
 *
 * Boolean columns have low cardinality (most rows have is_testing = 0), but MySQL
 * will still use the index when the WHERE clause targets the minority value (= 1),
 * since the selectivity is high for that specific value.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'idx_vends_is_testing';

    public function up(): void
    {
        if ($this->indexExists('vends', self::INDEX_NAME)) {
            return;
        }

        Schema::table('vends', function (Blueprint $table) {
            $table->index('is_testing', self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (! $this->indexExists('vends', self::INDEX_NAME)) {
            return;
        }

        Schema::table('vends', function (Blueprint $table) {
            $table->dropIndex(self::INDEX_NAME);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $connection->getDatabaseName())
            ->where('table_name', $connection->getTablePrefix() . $table)
            ->where('index_name', $name)
            ->exists();
    }
};
