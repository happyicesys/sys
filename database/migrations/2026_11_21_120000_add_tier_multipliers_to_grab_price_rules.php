<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A multiplier per RP tier (Brian, 2026-11-21: "allow user to set each RP with multiplier" — RP3 is already dearer,
 * so RP3 × 1.25 is too high). Read only when a rule's rp_type is 0 = "Site's own tier": each store then takes its
 * machine's Site tier (customers.selling_price_type) and that tier's multiplier. {"1": 12500, "2": 12500, "3": 10000}
 * in basis points; a tier missing from it uses multiplier_bp. NULL on every rule that names one tier.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['grab_menu_settings', 'grab_store_price_rules'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'tier_multipliers_json')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->json('tier_multipliers_json')->nullable()->after('multiplier_bp');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['grab_menu_settings', 'grab_store_price_rules'] as $tableName) {
            if (Schema::hasColumn($tableName, 'tier_multipliers_json')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('tier_multipliers_json');
                });
            }
        }
    }
};
