<?php

use App\Models\VendModel;

/**
 * APK OTA channels.
 *
 * mark1 ships more than one Android build: the legacy vending-machine APK and the
 * AI smart-freezer APK. They are independent products with independent versionCode
 * sequences, so "the latest build" is only meaningful *within a channel*.
 *
 * A channel is the unit of release: one apk_releases row belongs to exactly one
 * channel, and GET /ota/manifest only ever offers a device the build from its own
 * channel. Adding a third fleet later (a new board, a per-country build) is one
 * entry here plus a seeded vend model — no schema change, no new controller.
 *
 * Keys:
 *   label           Human label shown on the admin tab strip.
 *   package_name    Android applicationId. This is the PRIMARY key a device is
 *                   matched on (it sends ?package=), because it cannot be wrong —
 *                   unlike the vends.vend_model_id row, which is hand-maintained.
 *   vend_model      vend_models.name that identifies this channel's fleet, used for
 *                   the version-spread panel, "push OTA check", and as the fallback
 *                   when a device does not send its package name. NULL means
 *                   "everything not claimed by another channel" — i.e. the default
 *                   fleet. Exactly one channel should have NULL.
 *   storage_folder  Disk-relative folder the uploaded APK is stored under.
 *
 * NOTE: channel keys are persisted in apk_releases.channel. Renaming a key requires
 * a data migration; add a new key instead.
 */
return [

    /*
     * Channel used when a device sends neither a recognised package name nor maps to
     * a channel via its vend model. Must be a key in 'channels' below.
     */
    'default_channel' => env('OTA_DEFAULT_CHANNEL', 'vending'),

    /*
     * Minutes between persisted OTA check-in telemetry writes for the same machine.
     * A device polls on a fixed interval; without this every poll is a write to
     * vends. 0 disables throttling (write on every poll).
     */
    'checkin_throttle_minutes' => (int) env('OTA_CHECKIN_THROTTLE_MINUTES', 5),

    /*
     * Max upload size in kilobytes for a release binary (Laravel 'max:' rule).
     * php.ini upload_max_filesize / post_max_size must be at least this large.
     */
    'max_upload_kb' => (int) env('OTA_MAX_UPLOAD_KB', 262144), // 256 MB

    'channels' => [

        'vending' => [
            'label' => 'Vending Machine APK',
            'package_name' => env('OTA_PACKAGE_VENDING', 'com.venderroute'),
            'vend_model' => null, // default fleet: every model not claimed below
            'storage_folder' => 'sys/vends/apk/vending',
        ],

        'smart_freezer' => [
            'label' => 'Smart Freezer APK',
            // sg.mark1.freezer — the applicationId documented in
            // App\Services\SmartFreezerCatalogPush, written from first-hand
            // knowledge of the supplier APK's PushMessageParser/PushCoordinator.
            // NOT yet confirmed against the built binary (the freezer APK source
            // is not in this repo). OtaController logs a warning naming any
            // applicationId a device reports that matches no channel — check
            // storage/logs after a freezer polls, then correct this value (or set
            // OTA_PACKAGE_SMART_FREEZER) if it differs. Until it matches, a
            // freezer that reports its package is served NOTHING, which is the
            // safe direction.
            'package_name' => env('OTA_PACKAGE_SMART_FREEZER', 'sg.mark1.freezer'),
            'vend_model' => VendModel::SMART_VEND,
            'storage_folder' => 'sys/vends/apk/smart-freezer',
        ],

    ],

];
