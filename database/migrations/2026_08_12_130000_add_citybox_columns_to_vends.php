<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Citybox smart-chiller linkage (2026-08-12).
 *
 * citybox_equipment_id — their device id (hex string, e.g. "08DFF2CEB11").
 * It CANNOT live in vends.code (int); it is the external key the poller and
 * import flow match on. UNIQUE so the same chiller can never be imported
 * twice — the DB is the real duplicate guard, the Form Request rule is just
 * the friendly error.
 *
 * citybox_status_json — last polled supplier state (status, online,
 * heartbeats, per-product stock snapshot + last stock delta), mirroring the
 * freezer_status_json pattern. citybox_synced_at — when that poll happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->string('citybox_equipment_id', 64)->nullable()->unique()->after('machine_type');
            $table->json('citybox_status_json')->nullable()->after('freezer_status_json');
            $table->timestamp('citybox_synced_at')->nullable()->after('citybox_status_json');
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn(['citybox_equipment_id', 'citybox_status_json', 'citybox_synced_at']);
        });
    }
};
