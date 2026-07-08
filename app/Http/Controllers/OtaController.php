<?php

namespace App\Http\Controllers;

use App\Models\ApkRelease;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * OtaController — device-facing Smart-Freezer APK OTA manifest endpoint.
 *
 * Contract (matches the APK's OtaService / UpdateManifest data class):
 *
 *   GET /ota/manifest?vend_code={code}&versionCode={installed}
 *     200 -> UpdateManifest JSON when a newer published build is on offer
 *     204 -> no newer build (device is up to date)
 *
 * The response keys are EXACTLY the camelCase names the APK deserialises:
 *   versionCode, versionName, url, sha256, sizeBytes, mandatory,
 *   minSupportedVersionCode, rolloutPermille
 *
 * mark1 does NOT apply the staged-rollout gate here — it returns rolloutPermille and
 * the device applies its own stable-bucket gate (bucket(vend_code) < rolloutPermille),
 * exactly as the on-device OtaCoordinator expects. This keeps the canary cohort stable
 * and the server stateless. mark1 only decides which build is "live" (highest
 * version_code with status = published).
 *
 * Auth: intentionally unauthenticated for v1 (parity with the existing /menu device
 * endpoint). This is safe because the real security control is on-device: the APK
 * verifies the downloaded bytes against sha256 AND pins the signing certificate, so a
 * forged manifest cannot push a foreign APK. Harden later with per-machine auth if
 * desired (see project notes).
 */
class OtaController extends Controller
{
    public function manifest(Request $request)
    {
        $vendCode = $request->query('vend_code');
        // Accept either camelCase (device) or snake_case, default 0 = "fresh install".
        $currentVersionCode = (int) ($request->query('versionCode', $request->query('version_code', 0)));

        // Record fleet version telemetry when we can identify the machine.
        if ($vendCode !== null && $vendCode !== '') {
            $vend = Vend::where('code', $vendCode)->first();
            if ($vend) {
                $vend->forceFill([
                    'apk_version_code' => $currentVersionCode ?: null,
                    'apk_checked_in_at' => Carbon::now(),
                ])->save();
            }
        }

        $release = ApkRelease::query()->liveManifest()->first();

        // No published build, or the device is already on it (or newer) -> up to date.
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
}
