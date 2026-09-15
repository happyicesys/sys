<?php

namespace App\Services;

use App\Jobs\PublishMqtt;
use App\Models\ApkRelease;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * OtaCheckNudge — tell a machine to poll /ota/manifest now.
 *
 * An OTA_CHECK frame only shortens the wait: the device still pulls the manifest,
 * verifies the hash, applies its rollout bucket, waits for an idle window, and gives
 * up on a build after MAX_INSTALL_ATTEMPTS (mark1-apk OtaCoordinator). So a nudge can
 * never force an install or cause a retry storm; the worst case is one extra poll.
 *
 * Two callers:
 *   - "Push OTA check" on the APK OTA Updates page (whole channel, on demand)
 *   - ota:nudge-stale (nightly, only machines behind the latest published build)
 */
class OtaCheckNudge
{
    public function __construct(private OtaChannelResolver $channels) {}

    /** Queue one OTA_CHECK frame to a machine. */
    public function send(Vend $vend): void
    {
        PublishMqtt::dispatch('CM'.$vend->code, $this->frame($vend))->onQueue('high');
    }

    /** Signed CSV MQTT envelope carrying the OTA_CHECK command. */
    public function frame(Vend $vend): string
    {
        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => 'OTA_CHECK',
            'time' => Carbon::now()->timestamp,
            'action' => '',
            'mid' => $vend->code,
        ]));
        $contentLength = strlen($content);
        $key = $vend->private_key ?: config('vend.private_key', '123456789110138A');
        $md5 = md5($fid.','.$contentLength.','.$content.$key);

        return $fid.','.$contentLength.','.$content.','.$md5;
    }

    /** Highest published versionCode on a channel, or null when nothing is published. */
    public function latestPublishedVersion(string $channel): ?int
    {
        $version = ApkRelease::query()->liveManifest($channel)->value('version_code');

        return $version === null ? null : (int) $version;
    }

    /**
     * Online machines on a channel that run an OTA-capable build older than the
     * latest published one.
     *
     * - Version is Vend::reportedApkVersion() (max of the OTA check-in column and the
     *   PWRON apkver), so a machine whose check-in column is stale but whose PWRON
     *   already says "latest" is left alone.
     * - Below $minVersionCode the build has no OTA client and ignores OTA_CHECK, and
     *   0 means the machine never reported a version at all — both are skipped.
     * - A board family claimed by ANOTHER channel (e.g. ZC-83A → vending_small) is
     *   skipped: the catch-all fleet scope deliberately still contains those boards,
     *   but their versionCode stream is not comparable with this channel's.
     *
     * @return Collection<int, Vend>
     */
    public function staleTargets(string $channel, int $minVersionCode): Collection
    {
        $latest = $this->latestPublishedVersion($channel);

        if ($latest === null) {
            return collect();
        }

        $targets = collect();

        $this->channels->scopeFleet(Vend::query(), $channel)
            ->where('is_active', true)
            ->where('is_disposed', false)
            ->where('is_online', true)
            ->whereNotNull('code')
            ->select(['id', 'code', 'private_key', 'apk_version_code', 'apk_ver_json'])
            ->chunkById(500, function ($vends) use (&$targets, $channel, $latest, $minVersionCode) {
                foreach ($vends as $vend) {
                    $boardChannel = $this->channels->forDeviceType(data_get($vend->apk_ver_json, 'deviceType'));

                    if ($boardChannel !== null && $boardChannel !== $channel) {
                        continue;
                    }

                    $reported = $vend->reportedApkVersion();

                    if ($reported < $minVersionCode || $reported >= $latest) {
                        continue;
                    }

                    $targets->push($vend);
                }
            });

        return $targets;
    }
}
