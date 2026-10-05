<?php

namespace App\Services\Ota;

use App\Models\ApkRelease;
use App\Models\Vend;
use App\Services\OtaChannelResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * "Is there a newer build for this device?" — the one answer behind both OTA
 * transports:
 *
 *   - HTTPS  GET /ota/manifest (OtaController), every OTA-capable APK;
 *   - MQTT   OTAREQ -> OTAMANIFEST (ServeOtaManifestOverMqtt), big-board 309+,
 *            used when the device's HTTPS poll keeps failing (Air724 square module
 *            + VoicePing on the CMI-HK route: the HTTPS manifest request never
 *            completes there while MQTT stays up — apk/mark1-apk/UNRELEASED_V309.md).
 *
 * Both record the check-in (apk_version_code / apk_checked_in_at), so a board that
 * can only reach us over MQTT is no longer "never checked in" to the nudges.
 *
 * mark1 does not apply the staged-rollout gate here: it returns rolloutPermille and
 * the device applies its own stable bucket (see OtaController's docblock).
 */
class OtaManifestService
{
    public function __construct(private OtaChannelResolver $channels) {}

    /** The vend a device reports as vend_code, with what resolve() and the check-in need. */
    public function findVend(int|string|null $vendCode): ?Vend
    {
        if ($vendCode === null || $vendCode === '') {
            return null;
        }

        // apk_ver_json: OtaChannelResolver::resolve()'s board-family backstop reads
        // deviceType off it. Omit it and the backstop silently never fires.
        return Vend::query()
            ->select(['id', 'code', 'vend_model_id', 'apk_version_code', 'apk_checked_in_at', 'apk_ver_json', 'private_key'])
            ->bareCode($vendCode)
            ->first();
    }

    /**
     * The manifest for a device, or null when it is up to date, unknown, or there is
     * no published build for its channel. Records the check-in when $vend is known.
     *
     * @return array{versionCode:int,versionName:?string,url:?string,sha256:?string,sizeBytes:int,mandatory:bool,minSupportedVersionCode:int,rolloutPermille:int}|null
     */
    public function manifestFor(?Vend $vend, int $currentVersionCode, ?string $package, string $via = 'https'): ?array
    {
        if ($vend) {
            $this->recordCheckIn($vend, $currentVersionCode);
        }

        // A device that reports an applicationId we do not recognise gets NOTHING.
        // Serving the wrong binary is strictly worse than serving none: the device
        // would fail the signer pin and retry forever. Devices that report NO package
        // still resolve by vend model, which the legacy fleet relies on.
        if (trim((string) $package) !== '' && $this->channels->forPackage($package) === null) {
            Log::warning('OTA manifest: unrecognised applicationId, no build offered.', [
                'package' => (string) $package,
                'vend_code' => $vend?->code,
                'version_code' => $currentVersionCode,
                'via' => $via,
                'known_packages' => array_map(
                    fn ($key) => $this->channels->packageName($key),
                    $this->channels->keys()
                ),
            ]);

            return null;
        }

        $release = $this->liveRelease($package, $vend);

        if (! $release || $release->version_code <= $currentVersionCode) {
            return null;
        }

        return [
            'versionCode' => (int) $release->version_code,
            'versionName' => $release->version_name,
            'url' => $release->file_url,
            'sha256' => $release->sha256,
            'sizeBytes' => (int) $release->size_bytes,
            'mandatory' => (bool) $release->mandatory,
            'minSupportedVersionCode' => (int) $release->min_supported_version_code,
            'rolloutPermille' => (int) $release->rollout_permille,
        ];
    }

    /**
     * The published build this device may download: its channel's live release, and
     * only if it is exactly the version + sha the device asked for. Chunks are served
     * from this, never from an arbitrary release row.
     */
    public function downloadableRelease(?Vend $vend, ?string $package, int $versionCode, string $sha256): ?ApkRelease
    {
        if (trim((string) $package) !== '' && $this->channels->forPackage($package) === null) {
            return null;
        }
        $release = $this->liveRelease($package, $vend);

        if (! $release
            || (int) $release->version_code !== $versionCode
            || strcasecmp((string) $release->sha256, $sha256) !== 0) {
            return null;
        }

        return $release;
    }

    private function liveRelease(?string $package, ?Vend $vend): ?ApkRelease
    {
        $channel = $this->channels->resolve($package, $vend);

        return ApkRelease::query()->liveManifest($channel)->first();
    }

    /**
     * Fleet version telemetry. Refreshed only when the reported version changed or
     * the throttle window has elapsed, so a poll is not an UPDATE every time.
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
