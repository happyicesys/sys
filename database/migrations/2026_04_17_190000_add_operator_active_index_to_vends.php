<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite index on vends(operator_id, is_active, is_testing).
     *
     * The customer listing (VendController::indexCustomer) joins customers to vends
     * and filters by:
     *
     *   vends.operator_id IN (1, 27, 30, 38, 44)   ← from request->operators
     *   vends.is_active   = 1                        ← from status = 'active'
     *   vends.is_testing  = 0                        ← from status = 'active'
     *
     * Without an index covering these columns, MySQL must do a full table scan of
     * `vends` for every page load, then filter in memory.
     *
     * With (operator_id, is_active, is_testing), MySQL can:
     *   1. Seek directly to each operator_id range
     *   2. Read only rows where is_active = 1, is_testing = 0
     *   3. Use the customer_id (stored in each InnoDB leaf node via PK) for the join
     *
     * Note: customer_id is NOT included in the index because the join condition
     * vends.customer_id = customers.id is evaluated from the `customers` side, so
     * MySQL accesses vends via the clustered index (PK) for the row data anyway.
     * The filter index here reduces the candidate set before any heap access.
     */
    public function up(): void
    {
        if (!Schema::hasIndex('vends', 'idx_vends_operator_active_testing')) {
            Schema::table('vends', function (Blueprint $table) {
                $table->index(
                    ['operator_id', 'is_active', 'is_testing'],
                    'idx_vends_operator_active_testing'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('vends', 'idx_vends_operator_active_testing')) {
            Schema::table('vends', function (Blueprint $table) {
                $table->dropIndex('idx_vends_operator_active_testing');
            });
        }
    }
};
