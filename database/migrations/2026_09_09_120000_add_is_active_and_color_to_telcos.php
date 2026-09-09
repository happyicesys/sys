<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SimCard Packages are retired, not deleted (2026-09-09): simcards.telco_id
     * and every report reached through it would lose its label. A package is
     * deactivated instead, and only once no SIM on it is still bound to a
     * machine — the guard lives in TelcoController::toggleActivateDeactivate.
     *
     * `color` tints the "SimCard Package" badge on the Operation Dashboard so a
     * package is recognisable at a glance. One of Telco::COLORS; NULL keeps the
     * default blue the badge has always used.
     */
    public function up(): void
    {
        Schema::table('telcos', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('remarks');
            $table->string('color', 20)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('telcos', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'color']);
        });
    }
};
