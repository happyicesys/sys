<?php

namespace App\Jobs\Ota;

use App\Models\Vend;
use App\Services\MqttService;
use App\Services\Ota\OtaApkCache;
use App\Services\Ota\OtaManifestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Send one piece of a published APK to a board over MQTT (big-board 309+).
 *
 *   board  -> mark1  {"Type":"OTACHUNK","rid":18,"ver":308,"sha":"<sha256>","off":0,"len":49152,"pkg":"com.venderroute"}
 *   mark1  -> board  CM<code>: {"Type":"OTACHUNKDATA","rid":18,"off":0,"total":6718151,"data":"<base64>"}
 *                    or       {"Type":"OTACHUNKDATA","rid":18,"error":"<reason>"}
 *
 * The board pulls one piece at a time and asks for the next only after it has
 * written this one, so it sets the pace and resumes from its partial file after
 * any drop. It still verifies the whole file's sha256 AND the signing certificate
 * before installing, so nothing here is trusted on its own.
 *
 * Only the channel's live release, at exactly the requested version + sha, is
 * served (OtaManifestService::downloadableRelease), from a verified local copy
 * (OtaApkCache). Runs on the `low` queue: never in front of a payment.
 */
class ServeOtaChunkOverMqtt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public const MAX_CHUNK_BYTES = 65536;

    /** Per machine: ~140 pieces per transfer, one in flight; 4/s is ample headroom. */
    public const MAX_PER_MINUTE = 240;

    public function __construct(public int $vendId, public array $input) {}

    public function handle(OtaManifestService $manifests, OtaApkCache $cache, MqttService $mqtt): void
    {
        $vend = Vend::query()->find($this->vendId);
        if (! $vend) {
            return;
        }
        if (! RateLimiter::attempt('ota-mqtt-chunk:'.$vend->id, self::MAX_PER_MINUTE, fn () => true, 60)) {
            return; // the board times out and asks again
        }

        $rid = (int) ($this->input['rid'] ?? 0);
        $version = (int) ($this->input['ver'] ?? 0);
        $sha = strtolower((string) ($this->input['sha'] ?? ''));
        $offset = (int) ($this->input['off'] ?? -1);
        $length = min(self::MAX_CHUNK_BYTES, (int) ($this->input['len'] ?? 0));
        $package = isset($this->input['pkg']) ? (string) $this->input['pkg'] : null;

        $reply = ['Type' => 'OTACHUNKDATA', 'rid' => $rid];

        if ($version <= 0 || ! preg_match('/^[0-9a-f]{64}$/', $sha) || $offset < 0 || $length <= 0) {
            $mqtt->publishVend($vend, 1, $reply + ['error' => 'bad request']);

            return;
        }

        $fresh = $manifests->findVend($vend->code) ?? $vend;
        $release = $manifests->downloadableRelease($fresh, $package, $version, $sha);
        if (! $release) {
            // Superseded or unpublished since the board's manifest: it re-polls.
            $mqtt->publishVend($vend, 1, $reply + ['error' => 'not available']);

            return;
        }
        if ($offset >= (int) $release->size_bytes) {
            $mqtt->publishVend($vend, 1, $reply + ['error' => 'offset beyond end']);

            return;
        }

        try {
            $bytes = $cache->readRange($release, $offset, $length);
        } catch (\Throwable $e) {
            Log::error('OTA chunk over MQTT failed.', [
                'vend_code' => $vend->code, 'version' => $version, 'offset' => $offset, 'error' => $e->getMessage(),
            ]);
            $mqtt->publishVend($vend, 1, $reply + ['error' => 'server read failed']);

            return;
        }

        $mqtt->publishVend($vend, 1, $reply + [
            'off' => $offset,
            'total' => (int) $release->size_bytes,
            'data' => base64_encode($bytes),
        ]);

        if ($offset === 0) {
            Log::info('OTA transfer over MQTT started.', ['vend_code' => $vend->code, 'version' => $version]);
        } elseif ($offset + strlen($bytes) >= (int) $release->size_bytes) {
            Log::info('OTA transfer over MQTT sent its last piece.', ['vend_code' => $vend->code, 'version' => $version]);
        }
    }
}
