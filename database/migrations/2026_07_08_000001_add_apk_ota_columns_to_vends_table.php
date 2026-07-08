<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * vends: Smart-Freezer APK OTA check-in telemetry.
 *
 * Two additive, nullable columns. A Smart-Freezer records the APK versionCode it
 * is currently running each time it polls GET /ota/manifest, so the admin panel
 * can show the fleet's version spread and spot machines stuck on an old build.
 *
 * Nullable + defaulted NULL means every existing (legacy vending) row is untouched
 * and no existing query changes behaviour. Only Smart-Freezer OTA check-ins ever
 * write these.
 *
 *   apk_version_code   — the versionCode the device reported on its last OTA poll
 *   apk_checked_in_at  — timestamp of that last OTA poll
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->unsignedBigInteger('apk_version_code')->nullable()->after('firmware_ver');
            $table->timestamp('apk_checked_in_at')->nullable()->after('apk_version_code');
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn(['apk_version_code', 'apk_checked_in_at']);
        });
    }
};
