<?php

namespace App\Http\Controllers;

use App\Models\ApkRelease;
use App\Models\Vend;
use App\Services\OtaChannelResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * OtaController — device-facing APK OTA manifest endpoint.
 *
 * Contract (matches the APK's OtaService / UpdateManifest data class):
 *
 *   GET /ota/manifest?vend_code={code}&versionCode={installed}&package={applicationId}
 *     200 -> UpdateManifest JSON when a newer published build is on offer
 *     204 -> no newer build (device is up to date)
 *
 * The response keys are EXACTLY the camelCase names the APK deserialises, and
 * nothing else is added — a stricter parser on a supplier-built APK must not choke
 * on unexpected fields:
 *   versionCode, versionName, url, sha256, sizeBytes, mandatory,
 *   minSupportedVersionCode, rolloutPermille
 *
 * CHANNEL: mark1 serves more than one build (legacy vending APK, smart-freezer
 * APK). The channel is resolved from the applicationId the device reports, falling
 * back to the machine's vend model and then the configured default — so a vending
 * machine can never be handed the freezer's APK. See config/ota.php.
 *
 * mark1 does NOT apply the staged-rollout gate here — it returns rolloutPermille and
 * the device applies its own stable-bucket gate (bucket(vend_code) < rolloutPermille).
 * This keeps the canary cohort stable and the server stateless. mark1 only decides
 * which build is "live" per channel (highest version_code with status = published).
 *
 * Auth: intentionally unauthenticated for v1 (parity with the existing /menu device
 * endpoint). The real security control is on-device: the APK verifies the downloaded
 * bytes against sha256 AND pins the signing certificate, so a forged manifest cannot
 * push a foreign APK. Harden with per-machine auth when the device side supports it.
 */
class OtaController extends Controller
{
    public function __construct(private OtaChannelResolver $channels)
    {
    }

    public function manifest(Request $request)
    {
        $vendCode = $request->query('vend_code');
        // Accept either camelCase (device) or snake_case, default 0 = "fresh install".
        $currentVersionCode = (int) ($request->query('versionCode', $request->query('version_code', 0)));
        $package = $request->query('package', $request->query('packageName'));

        $vend = ($vendCode !== null && $vendCode !== '')
            ? Vend::query()->select(['id', 'code', 'vend_model_id', 'apk_version_code', 'apk_checked_in_at'])->where('code', $vendCode)->first()
            : null;

        if ($vend) {
            $this->recordCheckIn($vend, $currentVersionCode);
        }

        // A device that reports an applicationId we do not recognise gets NOTHING.
        //
        // Without this, an unknown package falls through resolve() to the vend's model
        // and then to the default channel — i.e. a typo'd or newly-renamed applicationId
        // silently receives the DEFAULT fleet's APK. Serving the wrong binary is strictly
        // worse than serving none: the device would fail the signer pin and retry forever.
        //
        // The warning is also the discovery mechanism for the smart-freezer applicationId,
        // which is not confirmed against a built binary yet (see config/ota.php). Poll the
        // log after a freezer checks in and the real value is right there.
        //
        // Devices that report NO package at all are unaffected — they still resolve by vend
        // model, which is what the legacy vending fleet relies on.
        if (trim((string) $package) !== '' && $this->channels->forPackage($package) === null) {
            Log::warning('OTA manifest: unrecognised applicationId, no build offered.', [
                'package' => (string) $package,
                'vend_code' => $vendCode,
                'version_code' => $currentVersionCode,
                'known_packages' => array_map(
                    fn ($key) => $this->channels->packageName($key),
                    $this->channels->keys()
                ),
            ]);

            return response()->noContent(); // 204
        }

        $channel = $this->channels->resolve($package, $vend);

        $release = ApkRelease::query()->liveManifest($channel)->first();

        // No published build for this channel, or the device is already on it (or
        // newer) -> up to date.
        if (! $release || $release->version_code <= $currentVersionCode) {
            return response()->noContent(); // 204
        }

        return response()->json([
            'versionCode' => (int) $release->version_code,
            'versionName' => $release->version_name,
            'url' => $release->file_url,
            'sha256' => $release->sha256,
            'sizeBytes' => (int) $release->size_bytes,
            'mandatory' => (bool) $release->mandatory,
            'minSupportedVersionCode' => (int) $release->min_supported_version_code,
            'rolloutPermille' => (int) $release->rollout_permille,
        ]);
    }

    /**
     * Fleet version telemetry.
     *
     * Written on every poll would be one UPDATE per machine per poll for a value
     * that almost never changes, so the timestamp is only refreshed when the
     * reported version actually changed or the throttle window has elapsed. That
     * makes apk_checked_in_at accurate to within the window, which is all the
     * "stuck on an old build" view needs.
     */
    private function recordCheckIn(Vend $vend, int $versionCode): void
    {
        $reported = $versionCode ?: null;
        $throttleMinutes = (int) config('ota.checkin_throttle_minutes', 5);

        $versionChanged = $vend->apk_version_code !== $reported;
        $stale = $throttleMinutes <= 0
            || $vend->apk_checked_in_at === null
            || Carbon::parse($vend->apk_checked_in_at)->lte(Carbon::now()->subMinutes($throttleMinutes));

        if (! $versionChanged && ! $stale) {
            return;
        }

        $vend->forceFill([
            'apk_version_code' => $reported,
            'apk_checked_in_at' => Carbon::now(),
        ])->save();
    }
}
