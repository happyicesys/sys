<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a covering index for the two remaining slow dashboard queries:
 *
 *   getMonthGraphData      — SUM(total_amount), SUM(total_count) GROUP BY year, month
 *   getActiveMachineGraph  — COUNT(DISTINCT vend_id)              GROUP BY year, month
 *
 * Both queries filter:   operator_id IN (...)  AND  year BETWEEN x AND y
 *                        AND vend_id NOT IN (excluded list)
 * Both group by:         year, month
 * Both aggregate over:   vend_id / total_amount / total_count
 *
 * The existing idx_operator_year_month (operator_id, year, month) narrows to the
 * right buckets but does NOT include vend_id, total_amount, or total_count.
 * MySQL must therefore do a "heap read" (clustered index lookup via the row's PK)
 * for every matching row to fetch those columns — up to 500K random IOs on a
 * large production table, accounting for 7-14 seconds of cold-query time.
 *
 * With (operator_id, year, month, vend_id, total_amount, total_count) MySQL can:
 *   1. Seek to operator_id range in the index
 *   2. Scan year range without leaving the index
 *   3. Read month, vend_id, total_amount, total_count directly from index leaves
 *   4. Apply NOT IN on vend_id from the index (no heap access)
 *   5. SUM / COUNT DISTINCT entirely from index data
 *   → Zero heap reads — query resolves as a pure index scan.
 *
 * Trade-off: 6-column index adds ~50 bytes per row to the secondary B-tree.
 * vend_records receives ~1 K inserts/day, so the maintenance overhead is minimal.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'idx_vr_monthly_summary';

    public function up(): void
    {
        if ($this->indexExists('vend_records', self::INDEX_NAME)) {
            return;
        }

        Schema::table('vend_records', function (Blueprint $table) {
            $table->index(
                ['operator_id', 'year', 'month', 'vend_id', 'total_amount', 'total_count'],
                self::INDEX_NAME
            );
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
