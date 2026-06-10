<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blind SKU — per-mapping blended unit cost rows.
 *
 * A blind parent's cost is the weighted blend of its children's current unit
 * costs, and because the child mix/weights are per-mapping, the blend is too.
 * So a parent has ONE blended unit_costs row PER mapping it appears in.
 *
 *   product_mapping_id NULL  => a normal unit cost row (every existing row).
 *   product_mapping_id set    => a derived/blended row for a parent in that
 *                                mapping; product_id = the parent product.
 *
 * Keeping these as real unit_costs rows means vend_transactions point their
 * unit_cost_id at them exactly like any other product, so gross-profit, CSV
 * export and every report keep working unchanged. Versioning still uses
 * is_current + date_from/date_to so historical transactions stay accurate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_costs', function (Blueprint $table) {
            if (!Schema::hasColumn('unit_costs', 'product_mapping_id')) {
                $table->unsignedBigInteger('product_mapping_id')->nullable()->after('product_id');
            }
        });

        Schema::table('unit_costs', function (Blueprint $table) {
            // Resolve "current blended cost for this parent in this mapping" fast.
            $table->index(['product_id', 'product_mapping_id', 'is_current'], 'unit_costs_blended_current_idx');
        });
    }

    public function down(): void
    {
        Schema::table('unit_costs', function (Blueprint $table) {
            $table->dropIndex('unit_costs_blended_current_idx');
        });

        Schema::table('unit_costs', function (Blueprint $table) {
            if (Schema::hasColumn('unit_costs', 'product_mapping_id')) {
                $table->dropColumn('product_mapping_id');
            }
        });
    }
};
