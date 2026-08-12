<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Machine identity on the vend itself (2026-08-12): Vending Machine / Smart Freezer /
 * Smart Chiller, set from Setting → Edit.
 *
 * Previously "smart freezer" was INFERRED from the bound mapping's is_smart — cause and effect
 * inverted: binding the wrong mapping silently redefined what the machine was. Now the vend
 * declares its kind and the mapping dropdowns/validation are restricted to match
 * (VendController::update guard). vend_type_id (Combi/DVM/FVM/Model-E/F) is a different, unused
 * axis (hardware model, NULL on all rows) and is deliberately left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->string('machine_type', 20)->default('vending_machine')->index()->after('vend_type_id');
        });

        // Backfill from today's inference so existing smart freezers keep their identity:
        // any vend currently bound to a smart mapping is a smart freezer.
        DB::statement("
            UPDATE vends
            JOIN product_mappings ON product_mappings.id = vends.product_mapping_id
            SET vends.machine_type = 'smart_freezer'
            WHERE product_mappings.is_smart = 1
        ");
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn('machine_type');
        });
    }
};
