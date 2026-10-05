<?php

namespace App\Http\Controllers;

use App\Services\Ota\OtaManifestService;
use Illuminate\Http\Request;

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
    public function __construct(private OtaManifestService $manifests) {}

    public function manifest(Request $request)
    {
        // Accept either camelCase (device) or snake_case, default 0 = "fresh install".
        $currentVersionCode = (int) ($request->query('versionCode', $request->query('version_code', 0)));
        $package = $request->query('package', $request->query('packageName'));

        $vend = $this->manifests->findVend($request->query('vend_code'));
        $manifest = $this->manifests->manifestFor($vend, $currentVersionCode, $package);

        // No published build for this channel, an unknown package, or the device is
        // already on it (or newer) -> up to date.
        return $manifest === null
            ? response()->noContent() // 204
            : response()->json($manifest);
    }
}
