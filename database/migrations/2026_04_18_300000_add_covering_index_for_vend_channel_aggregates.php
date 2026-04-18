<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a covering index for the three vend_channels aggregate sort subqueries
 * on the CustomerIndex page:
 *
 *   vc        — SUM(amount * qty),  SUM(amount * capacity)  GROUP BY vend_id
 *   vc_cost   — JOIN unit_costs ON product_id               GROUP BY vend_id
 *   vc_stock  — JOIN products ON product_id                 GROUP BY vend_id
 *
 * All three filter:  is_active = true  AND  capacity > 0
 * All three group:   vend_id
 * All three select:  amount, qty (vc), or product_id (vc_cost, vc_stock)
 *
 * The existing idx_vc_vid_active_cap (vend_id, is_active, capacity) leads with
 * vend_id, which is NOT in the WHERE clause — MySQL cannot use it to filter
 * is_active/capacity and must perform a full table scan (~6–9 seconds per query).
 *
 * With (is_active, capacity, vend_id, amount, qty, product_id):
 *   1. Seek to is_active = 1  in the index
 *   2. Range on capacity > 0  within that partition
 *   3. Read vend_id, amount, qty, product_id directly from index leaf nodes
 *   4. GROUP BY vend_id / JOIN key (product_id) resolved from index — zero heap reads
 *
 * This also improves the loadAggregates vc, vc_cost, vc_stock queries which
 * share the same WHERE pattern but are scoped to 50 known vend_ids.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'idx_vc_active_cap_covering';

    public function up(): void
    {
        if ($this->indexExists('vend_channels', self::INDEX_NAME)) {
            return;
        }

        Schema::table('vend_channels', function (Blueprint $table) {
            $table->index(
                ['is_active', 'capacity', 'vend_id', 'amount', 'qty', 'product_id'],
                self::INDEX_NAME
            );
        });
    }

    public function down(): void
    {
        if (! $this->indexExists('vend_channels', self::INDEX_NAME)) {
            return;
        }

        Schema::table('vend_channels', function (Blueprint $table) {
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
