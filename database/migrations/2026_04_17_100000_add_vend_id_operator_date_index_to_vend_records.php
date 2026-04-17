<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a composite index (vend_id, operator_id, date) to vend_records.
 *
 * When the dashboard performance page filters by machine code(s) + operator(s) + date range,
 * MySQL previously had to choose between idx_vend_date (vend_id, date) and
 * idx_operator_date_vend (operator_id, date, vend_id).  This new index covers all three
 * columns in the order MySQL most naturally applies them for the dashboard queries,
 * allowing it to resolve the entire WHERE clause from the index alone.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'idx_vend_operator_date';

    public function up(): void
    {
        if ($this->indexExists('vend_records', self::INDEX_NAME)) {
            return;
        }

        Schema::table('vend_records', function (Blueprint $table) {
            $table->index(['vend_id', 'operator_id', 'date'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (! $this->indexExists('vend_records', self::INDEX_NAME)) {
            return;
        }

        Schema::table('vend_records', function (Blueprint $table) {
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
