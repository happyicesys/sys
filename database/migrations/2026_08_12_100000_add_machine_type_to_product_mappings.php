<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mapping taxonomy (2026-08-12): vending_machine / smart_freezer / smart_chiller.
 *
 * is_smart stays — it is specifically the "freezer planogram editor" switch the ProductMapping
 * Edit UI and getVendMenu branch on, and it is kept in sync by the controllers (invariant:
 * is_smart === (machine_type === 'smart_freezer')). machine_type is the richer taxonomy the
 * vend-side mapping restriction keys on; a boolean could never distinguish freezer from chiller
 * mappings (chiller allows multiple products per channel level — different layout rules later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_mappings', function (Blueprint $table) {
            $table->string('machine_type', 20)->default('vending_machine')->index()->after('is_smart');
        });

        // Backfill: every mapping flagged smart today IS a smart-freezer planogram (chiller
        // mappings do not exist yet — citybox integration is API-only so far).
        DB::table('product_mappings')->where('is_smart', 1)->update(['machine_type' => 'smart_freezer']);
    }

    public function down(): void
    {
        Schema::table('product_mappings', function (Blueprint $table) {
            $table->dropColumn('machine_type');
        });
    }
};
