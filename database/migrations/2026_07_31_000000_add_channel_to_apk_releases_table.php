<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * apk_releases: split the release catalogue into OTA channels.
 *
 * mark1 ships two independent Android builds — the legacy vending APK
 * (com.venderroute) and the smart-freezer APK — each with its own versionCode
 * sequence. The original table assumed a single global "latest build", which would
 * have offered a vending machine the freezer's APK (and made version_code collide
 * across two unrelated products).
 *
 *   channel       which fleet the build belongs to (config/ota.php key)
 *   package_name  the Android applicationId, so a device that reports its own
 *                 package can be matched without trusting vends.vend_model_id
 *
 * version_code therefore becomes unique PER CHANNEL, not globally: vending 301 and
 * freezer 301 are different builds and must be allowed to coexist.
 *
 * Safe by construction: additive columns with a default, and the table is empty in
 * production (no release has been uploaded yet). Existing rows in any other
 * environment are backfilled onto the default channel. Nothing the legacy vending
 * fleet reads at runtime is touched — apk_releases is only read by /ota/manifest.
 *
 * Index changes are guarded so a partially-applied run can be repeated safely.
 */
return new class extends Migration
{
    private const UNIQUE_OLD = 'apk_releases_version_code_unique';
    private const UNIQUE_NEW = 'apk_releases_channel_version_unique';
    private const INDEX_OLD = 'apk_releases_status_version_index';
    private const INDEX_NEW = 'apk_releases_channel_status_version_index';

    public function up(): void
    {
        $defaultChannel = (string) config('ota.default_channel', 'vending');
        $defaultPackage = (string) config("ota.channels.{$defaultChannel}.package_name", '');

        Schema::table('apk_releases', function (Blueprint $table) use ($defaultChannel) {
            if (! Schema::hasColumn('apk_releases', 'channel')) {
                $table->string('channel', 40)->default($defaultChannel)->after('id');
            }
            if (! Schema::hasColumn('apk_releases', 'package_name')) {
                $table->string('package_name')->nullable()->after('channel');
            }
        });

        // Backfill pre-existing rows onto the default channel (no-op in production).
        DB::table('apk_releases')
            ->whereNull('package_name')
            ->update([
                'channel' => $defaultChannel,
                'package_name' => $defaultPackage !== '' ? $defaultPackage : null,
            ]);

        Schema::table('apk_releases', function (Blueprint $table) {
            // version_code is only unique within a channel now.
            if ($this->hasIndex(self::UNIQUE_OLD)) {
                $table->dropUnique(self::UNIQUE_OLD);
            }
            if (! $this->hasIndex(self::UNIQUE_NEW)) {
                $table->unique(['channel', 'version_code'], self::UNIQUE_NEW);
            }

            // The live-manifest lookup is always channel + status + highest version,
            // which makes the old (status, version_code) index redundant.
            if (! $this->hasIndex(self::INDEX_NEW)) {
                $table->index(['channel', 'status', 'version_code'], self::INDEX_NEW);
            }
            if ($this->hasIndex(self::INDEX_OLD)) {
                $table->dropIndex(self::INDEX_OLD);
            }
        });
    }

    public function down(): void
    {
        Schema::table('apk_releases', function (Blueprint $table) {
            if (! $this->hasIndex(self::INDEX_OLD)) {
                $table->index(['status', 'version_code'], self::INDEX_OLD);
            }
            if ($this->hasIndex(self::INDEX_NEW)) {
                $table->dropIndex(self::INDEX_NEW);
            }
            if ($this->hasIndex(self::UNIQUE_NEW)) {
                $table->dropUnique(self::UNIQUE_NEW);
            }
            if (! $this->hasIndex(self::UNIQUE_OLD)) {
                $table->unique('version_code', self::UNIQUE_OLD);
            }
        });

        Schema::table('apk_releases', function (Blueprint $table) {
            foreach (['channel', 'package_name'] as $column) {
                if (Schema::hasColumn('apk_releases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /** Whether a named index currently exists on apk_releases. */
    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('apk_releases'))
            ->contains(fn ($index) => ($index['name'] ?? null) === $name);
    }
};
