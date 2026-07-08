<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * apk_releases: Smart-Freezer APK OTA release catalogue.
 *
 * One row per uploaded, signed Smart-Freezer APK build. The freezer fleet polls
 * GET /ota/manifest and this table is the source of truth for what build is on
 * offer. Entirely additive and self-contained — legacy vending machines never
 * read this table (they have their own VMC firmware path via vends.firmware_ver),
 * so nothing here can affect the existing fleet.
 *
 * Columns map 1:1 onto the APK's UpdateManifest data class:
 *   version_code                -> versionCode   (strictly-increasing install gate)
 *   version_name                -> versionName   (human label)
 *   file_url                    -> url           (HTTPS URL of the signed APK)
 *   sha256                      -> sha256         (verified on-device before install)
 *   size_bytes                  -> sizeBytes      (free-space pre-check on the 16GB board)
 *   mandatory                   -> mandatory      (bypasses the staged-rollout gate)
 *   min_supported_version_code  -> minSupportedVersionCode (EOL marker, informational)
 *   rollout_permille            -> rolloutPermille (0..1000 staged-rollout gate)
 *
 * status controls whether a release is offered at all:
 *   draft     — uploaded, verified, NOT offered to the fleet yet
 *   published — offered; the highest-version_code published row is the live manifest
 *   archived  — retired, kept for history/audit, never offered
 *
 * file_path is the disk-relative path (for Storage delete/exists); file_url is the
 * served/CDN URL handed to the device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apk_releases', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('version_code')->unique();
            $table->string('version_name');

            $table->string('file_url');
            $table->string('file_path');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size_bytes')->default(0);

            $table->boolean('mandatory')->default(false);
            $table->unsignedBigInteger('min_supported_version_code')->default(0);
            $table->unsignedSmallInteger('rollout_permille')->default(0); // 0..1000

            $table->string('status')->default('draft'); // draft | published | archived
            $table->text('release_notes')->nullable();

            $table->unsignedBigInteger('uploaded_by')->nullable();

            $table->timestamps();

            $table->index(['status', 'version_code'], 'apk_releases_status_version_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apk_releases');
    }
};
