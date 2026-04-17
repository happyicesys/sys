<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a covering index on unit_costs(is_current, product_id, cost).
     *
     * The vc_cost query joins vend_channels directly to unit_costs on product_id
     * and filters is_current = 1, then reads cost for the SUM aggregation.
     *
     * Without this index MySQL does a full scan of unit_costs for every matched
     * vend_channel row to find the current cost. With the index:
     *   1. is_current = 1 filters immediately (high selectivity — only one
     *      active cost per product at a time)
     *   2. product_id resolves the join from the index
     *   3. cost is read directly from the index leaf node (covering index)
     *      with no heap lookup required
     */
    public function up(): void
    {
        if (!Schema::hasIndex('unit_costs', 'idx_uc_current_product_cost')) {
            Schema::table('unit_costs', function (Blueprint $table) {
                $table->index(['is_current', 'product_id', 'cost'], 'idx_uc_current_product_cost');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('unit_costs', 'idx_uc_current_product_cost')) {
            Schema::table('unit_costs', function (Blueprint $table) {
                $table->dropIndex('idx_uc_current_product_cost');
            });
        }
    }
};
