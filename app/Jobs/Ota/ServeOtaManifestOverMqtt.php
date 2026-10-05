<?php

namespace App\Jobs\Ota;

use App\Models\Vend;
use App\Services\MqttService;
use App\Services\Ota\OtaManifestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Answer a board's MQTT OTA check (big-board 309+).
 *
 *   board  -> mark1  {"Type":"OTAREQ","rid":17,"ver":307,"pkg":"com.venderroute"}
 *   mark1  -> board  CM<code>: {"Type":"OTAMANIFEST","rid":17,"manifest":{...}|null}
 *
 * The board asks this way only after its HTTPS manifest poll has failed several
 * times in a row (the CMI-HK route, apk/mark1-apk/UNRELEASED_V309.md). The answer
 * is OtaManifestService's — the same one GET /ota/manifest gives, check-in
 * included — so the two transports can never disagree. `rid` is echoed so the
 * board can match the reply to its request.
 */
class ServeOtaManifestOverMqtt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    /** Per machine: a board polls every 6 h, a manual nudge a few times; this is generous. */
    public const MAX_PER_MINUTE = 6;

    public function __construct(public int $vendId, public array $input) {}

    public function handle(OtaManifestService $manifests, MqttService $mqtt): void
    {
        $vend = Vend::query()->find($this->vendId);
        if (! $vend) {
            return;
        }
        if (! RateLimiter::attempt('ota-mqtt-manifest:'.$vend->id, self::MAX_PER_MINUTE, fn () => true, 60)) {
            return;
        }

        $rid = (int) ($this->input['rid'] ?? 0);
        $version = (int) ($this->input['ver'] ?? 0);
        $package = isset($this->input['pkg']) ? (string) $this->input['pkg'] : null;

        $fresh = $manifests->findVend($vend->code) ?? $vend;
        $manifest = $manifests->manifestFor($fresh, $version, $package, 'mqtt');

        $mqtt->publishVend($vend, 1, [
            'Type' => 'OTAMANIFEST',
            'rid' => $rid,
            'manifest' => $manifest,
            'time' => now()->timestamp,
        ]);

        Log::info('OTA manifest served over MQTT.', [
            'vend_code' => $vend->code,
            'installed' => $version,
            'offered' => $manifest['versionCode'] ?? null,
        ]);
    }
}
