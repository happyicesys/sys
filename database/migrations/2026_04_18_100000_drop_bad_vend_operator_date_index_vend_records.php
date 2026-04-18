<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REGRESSION FIX — drop idx_vend_operator_date from vend_records.
 *
 * Root cause of the April 18 production outage (50s page load):
 *
 * Commit 9d50b91c3 (Apr 17 "optimize queue") added the composite index
 *   idx_vend_operator_date  (vend_id, operator_id, date)
 * to vend_records.
 *
 * The dashboard's getMonthGraphData and getActiveMachineGraphData queries both do:
 *   WHERE operator_id IN (x, y)
 *     AND date BETWEEN <last_year_start> AND <this_year_end>
 *     AND vend_id NOT IN (<62 testing-machine IDs>)
 *
 * Before this index MySQL correctly chose idx_operator_date_vend (operator_id, date, vend_id)
 * and resolved the 2-year scan in < 1 second.
 *
 * After this index MySQL's query optimizer—seeing "vend_id NOT IN (62 values)" and a
 * vend_id-leading index—switched to idx_vend_operator_date.  With NOT IN on the leading
 * column MySQL cannot do a single range scan; instead it does 63 separate range scans
 * across every gap between the 62 excluded vend IDs, effectively scanning the entire
 * 2-year vend_records table 63 times.  Production result: 50 seconds per query.
 *
 * The existing idx_operator_date_vend index already covers all dashboard queries
 * efficiently.  The new index helps only when vend_id is a positive IN filter
 * (machine-code searches), but that path is already fast via the small resolved
 * ID list — the index is not needed there either.
 *
 * Dropping idx_vend_operator_date restores the pre-regression query plan.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'idx_vend_operator_date';

    public function up(): void
    {
        if (! $this->indexExists('vend_records', self::INDEX_NAME)) {
            return;
        }

        Schema::table('vend_records', function (Blueprint $table) {
            $table->dropIndex(self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if ($this->indexExists('vend_records', self::INDEX_NAME)) {
            return;
        }

        Schema::table('vend_records', function (Blueprint $table) {
            $table->index(['vend_id', 'operator_id', 'date'], self::INDEX_NAME);
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
