<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add index on delivery_product_mapping_vend(vend_id).
     *
     * The customer vend listing eager-loads the delivery platform chain:
     *   vend → deliveryProductMappingVends → deliveryProductMapping
     *         → deliveryPlatformOperator → deliveryPlatform
     *
     * Step 1 of this chain queries delivery_product_mapping_vend by vend_id
     * for all vends on the current page (~50 IDs). Without an index on vend_id,
     * this is a full table scan on every page load. All subsequent steps in the
     * chain are fast PK lookups, but they can't start until step 1 finishes —
     * so the latency accumulates and shows up on the final delivery_platforms query
     * in Telescope.
     *
     * Existing indexes cover delivery_product_mapping_id, customer_id, and
     * platform_ref_id+vend_code — but vend_id is unindexed.
     */
    public function up(): void
    {
        if (!Schema::hasIndex('delivery_product_mapping_vend', 'idx_dpmv_vend_id')) {
            Schema::table('delivery_product_mapping_vend', function (Blueprint $table) {
                $table->index('vend_id', 'idx_dpmv_vend_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('delivery_product_mapping_vend', 'idx_dpmv_vend_id')) {
            Schema::table('delivery_product_mapping_vend', function (Blueprint $table) {
                $table->dropIndex('idx_dpmv_vend_id');
            });
        }
    }
};
