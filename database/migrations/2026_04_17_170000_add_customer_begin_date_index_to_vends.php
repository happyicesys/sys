<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite index on vends(customer_id, begin_date, created_at).
     *
     * The Customer::vend() hasOne relationship is defined as:
     *   hasOne(Vend::class)->latest('begin_date')->latest('created_at')
     *
     * When eager-loaded for a page of 50 customers, Laravel generates:
     *   SELECT * FROM vends
     *   WHERE customer_id IN (50 IDs)
     *   ORDER BY begin_date DESC, created_at DESC
     *
     * The existing idx_vends_customer_id(customer_id) covers the WHERE but leaves
     * MySQL doing a full filesort over every matched row to satisfy the ORDER BY.
     *
     * With (customer_id, begin_date, created_at), MySQL can:
     *   1. Seek to each customer_id in the index
     *   2. Read rows already ordered by begin_date → created_at
     *   3. Merge 50 pre-sorted streams — far cheaper than a blind filesort
     *
     * Note: the existing single-column index idx_vends_customer_id becomes
     * redundant once this is in place (MySQL will prefer the composite index
     * for all queries that filter by customer_id), but it is left in place
     * to avoid a migration that drops it unnecessarily.
     */
    public function up(): void
    {
        if (!Schema::hasIndex('vends', 'idx_vends_customer_begin_created')) {
            Schema::table('vends', function (Blueprint $table) {
                $table->index(
                    ['customer_id', 'begin_date', 'created_at'],
                    'idx_vends_customer_begin_created'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('vends', 'idx_vends_customer_begin_created')) {
            Schema::table('vends', function (Blueprint $table) {
                $table->dropIndex('idx_vends_customer_begin_created');
            });
        }
    }
};
