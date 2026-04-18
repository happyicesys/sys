<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a covering index on ops_job_items for the CustomerIndex sort subqueries.
 *
 * The three slow sort subqueries (last_ops_jobs rn=1, last_second_ops_jobs rn=2,
 * last_thirty_days_stock_in) all share this inner scan pattern:
 *
 *   FROM ops_job_items oji_inner
 *   WHERE oji_inner.customer_id IN (<865 operator customer IDs>)
 *   AND   oji_inner.status >= 3 AND oji_inner.status <> 99
 *   -- plus reads: cash_amount, acc_total_amount, acc_total_count, ops_job_id
 *
 * The existing idx_oji_cust_created (customer_id, created_at) lets MySQL probe
 * by customer_id and order by created_at — correct key order — but every row
 * then requires a heap read to fetch status, cash_amount, acc_total_amount,
 * acc_total_count, and ops_job_id.  On a large table with many customers these
 * heap reads dominate (~21 s for 865-customer HIPL group).
 *
 * With (customer_id, created_at, status, cash_amount, acc_total_amount,
 *        acc_total_count, ops_job_id):
 *
 *   1. customer_id IN (list) → BKA probes via index prefix — no full scan
 *   2. created_at            → leaf nodes already in PARTITION/ORDER BY sequence
 *                              → ROW_NUMBER window computed streaming, no filesort
 *   3. status                → WHERE filter evaluated from index leaf, zero heap reads
 *   4. cash_amount, acc_total_amount, acc_total_count, ops_job_id
 *                            → all covered, zero heap reads for the inner subquery
 *   5. id (PK)               → always appended to InnoDB secondary index leaf nodes,
 *                              so ops_job_item_channels join also zero-cost
 *
 * The existing idx_oji_cust_created becomes a subset of this index.  It is kept
 * to avoid any risk during rollout; MySQL will prefer the wider covering index for
 * queries that need the additional columns.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'idx_oji_cust_created_status_covering';

    public function up(): void
    {
        if ($this->indexExists('ops_job_items', self::INDEX_NAME)) {
            return;
        }

        Schema::table('ops_job_items', function (Blueprint $table) {
            $table->index(
                [
                    'customer_id',
                    'created_at',
                    'status',
                    'cash_amount',
                    'acc_total_amount',
                    'acc_total_count',
                    'ops_job_id',
                ],
                self::INDEX_NAME
            );
        });
    }

    public function down(): void
    {
        if (! $this->indexExists('ops_job_items', self::INDEX_NAME)) {
            return;
        }

        Schema::table('ops_job_items', function (Blueprint $table) {
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
