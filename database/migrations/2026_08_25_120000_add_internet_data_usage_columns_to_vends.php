<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * vends: cumulative data-usage counters reported by the machine's APK.
 *
 * SUPERSEDED by 2026_08_26_160000_rename_vend_data_usage_columns_to_kb: the
 * unit is now decimal KB and the columns are internet_data_kb / _mobile_kb /
 * _app_kb. This file is left as it ran (batch 437) — read the rename migration
 * for the current names, units and wire keys. Do NOT edit this one to "fix"
 * the unit: it has already run everywhere and would never re-run.
 *
 * From APK v303 (mark1-apk) the VENDER packet's "Internet" object carries the
 * DataUsageLedger's readings — lifetime decimal MB, monotonic across reboots:
 *
 *   "DataMB":1843          all apps, all interfaces (rx+tx)
 *   "DataMobileMB":1790    cellular interface only — the SIM-plan figure
 *   "DataAppMB":211        the APK's own uid
 *   "DataDays":38          whole days the ledger has been metering
 *
 * These columns hold the LATEST cumulative value per machine; usage over a
 * window is computed by diffing the daily rows `vend:snapshot-data-usage`
 * copies into vend_data_usage_snapshots — the columns themselves are not a
 * history. A value can legitimately DECREASE (APK reinstall / prefs wipe
 * resets the ledger); ingest accepts the new truth rather than clamping.
 *
 * Additive and nullable like the internet_* set: older APKs never write them,
 * and NULL reads as "this machine has not told us", not as zero usage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->unsignedBigInteger('internet_data_mb')->nullable()->after('internet_updated_at');
            $table->unsignedBigInteger('internet_data_mobile_mb')->nullable()->after('internet_data_mb');
            $table->unsignedBigInteger('internet_data_app_mb')->nullable()->after('internet_data_mobile_mb');
            $table->unsignedSmallInteger('internet_data_days')->nullable()->after('internet_data_app_mb');
            $table->timestamp('internet_data_updated_at')->nullable()->after('internet_data_days');
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn([
                'internet_data_mb',
                'internet_data_mobile_mb',
                'internet_data_app_mb',
                'internet_data_days',
                'internet_data_updated_at',
            ]);
        });
    }
};
