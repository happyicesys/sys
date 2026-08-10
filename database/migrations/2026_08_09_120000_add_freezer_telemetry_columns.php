<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart-freezer telemetry columns.
 *
 * `freezer_status_json` mirrors the existing `parameter_json` / `acb_status_json` pattern: a latest
 * -state snapshot written by the device, merged in place with JSON_MERGE_PATCH so a status packet and
 * a self-check packet can share the column without a read-modify-write race.
 *
 * The power columns exist because a mains cut and a network drop are indistinguishable today —
 * `SyncOnlineStatus` can only infer "power restored" from a machine coming back within 15 minutes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->json('freezer_status_json')->nullable()->after('acb_status_json');
            $table->timestamp('last_power_cut_at')->nullable()->after('freezer_status_json');
            $table->timestamp('last_power_restored_at')->nullable()->after('last_power_cut_at');
            $table->boolean('is_on_battery')->default(false)->after('last_power_restored_at');
        });

        Schema::table('vend_alert_settings', function (Blueprint $table) {
            // Suppresses the temperature-alert *raisers* only. Data recording (vend_temps,
            // t1_lowest_48h, vend_temp_metrics) and the connectivity / no-transaction / stockout
            // checks continue regardless. Default true preserves existing fleet behaviour.
            $table->boolean('is_temp_alert_enabled')->default(true)->after('offline_after_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn([
                'freezer_status_json',
                'last_power_cut_at',
                'last_power_restored_at',
                'is_on_battery',
            ]);
        });

        Schema::table('vend_alert_settings', function (Blueprint $table) {
            $table->dropColumn('is_temp_alert_enabled');
        });
    }
};
