<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Machine Prefixes become soft-deletable.
 *
 * `vend_prefix_id` is a denormalised snapshot column on vend_transactions,
 * vend_records, stock_counts, customer_vend_bindings, vend_product_records,
 * gp_metrics and ops_machine_daily_snapshots, and `vend_prefixes` carries NO
 * foreign-key constraint. A hard DELETE therefore silently orphans every one of
 * those historical rows and blanks the prefix column on the reports that
 * leftJoin('vend_prefixes', ...).
 *
 * Soft-deleting hides a retired prefix from every list and dropdown while the
 * reports' raw joins still resolve its name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vend_prefixes', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('vend_prefixes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
