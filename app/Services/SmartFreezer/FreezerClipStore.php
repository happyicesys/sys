<?php

namespace App\Services\SmartFreezer;

use App\Models\SmartFreezerClip;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\Process\Process;

/**
 * The only writer of `smart_freezer_clips`: stores one door-session recording the freezer app
 * uploads (camera 5 today — Brian, 2026-10-13: "for our own records") privately on the default
 * disk (DO Spaces in prod) and records it against the session ref.
 *
 * The freezer has already cut the clip to door-close and shrunk it (640×360 H.264); mark1 checks
 * it is an mp4 of a sane size, never re-encodes it. A re-upload of the same session + camera
 * replaces the file (same path) — the freezer retries until it hears a 2xx.
 */
class FreezerClipStore
{
    /** Our txnRef = the TRADE's SFREF: SF-<vend code>-<epoch s>-<seq>. */
    public const SESSION_REF_PATTERN = '/^SF-(\d+)-\d{9,11}-\d+$/';

    public const MAX_CAMERA_ID = 7;

    /**
     * @param  array{recorded_from_ms?: ?int, door_closed_ms?: ?int, duration_ms?: ?int, meta?: array}  $facts  what the freezer reported
     *
     * @throws InvalidArgumentException a refusal the device should not retry
     */
    public function store(Vend $vend, string $sessionRef, int $cameraId, string $body, array $facts = []): SmartFreezerClip
    {
        if (! preg_match(self::SESSION_REF_PATTERN, $sessionRef, $m) || (string) $m[1] !== (string) $vend->code) {
            throw new InvalidArgumentException("session ref {$sessionRef} is not a session of machine {$vend->code}");
        }
        if ($cameraId < 0 || $cameraId > self::MAX_CAMERA_ID) {
            throw new InvalidArgumentException("camera {$cameraId} is out of range");
        }
        $bytes = strlen($body);
        if ($bytes === 0) {
            throw new InvalidArgumentException('empty body');
        }
        if ($bytes > (int) config('smart_freezer.clips.max_bytes')) {
            throw new InvalidArgumentException("clip of {$bytes} bytes is over the limit");
        }
        // ISO BMFF: the first box is `ftyp`, its type at bytes 4..7.
        if (substr($body, 4, 4) !== 'ftyp') {
            throw new InvalidArgumentException('not an mp4');
        }

        $path = sprintf('freezer-videos/%d/%s-cam%d.mp4', $vend->id, $sessionRef, $cameraId);
        Storage::put($path, $body, ['visibility' => 'private', 'mimetype' => 'video/mp4']);

        $clip = SmartFreezerClip::query()->firstOrNew([
            'vend_id' => $vend->id, 'session_ref' => $sessionRef, 'camera_id' => $cameraId,
        ]);
        $replaced = $clip->exists;
        $clip->fill([
            'path' => $path,
            'bytes' => $bytes,
            'duration_ms' => $this->probeDurationMs($body) ?? $this->positive($facts['duration_ms'] ?? null),
            'recorded_from' => $this->epochMs($facts['recorded_from_ms'] ?? null),
            'door_closed_at' => $this->epochMs($facts['door_closed_ms'] ?? null),
            'meta' => ($facts['meta'] ?? []) ?: null,
        ])->save();

        Log::info('freezer clip stored', [
            'vend' => $vend->code, 'session_ref' => $sessionRef, 'camera' => $cameraId,
            'bytes' => $bytes, 'duration_ms' => $clip->duration_ms, 'replaced' => $replaced,
        ]);

        return $clip;
    }

    /** ffprobe's reading of the uploaded file, when the binary is there; else the device's figure stands. */
    private function probeDurationMs(string $body): ?int
    {
        $bin = (string) config('smart_freezer.clips.ffprobe_bin');
        if ($bin === '' || ! is_executable($bin)) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'clip');
        try {
            file_put_contents($tmp, $body);
            $process = new Process([$bin, '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', $tmp]);
            $process->setTimeout(20);
            $process->run();
            $seconds = trim($process->getOutput());

            return $process->isSuccessful() && is_numeric($seconds) ? (int) round((float) $seconds * 1000) : null;
        } catch (\Throwable $e) {
            Log::warning('freezer clip ffprobe failed', ['error' => $e->getMessage()]);

            return null;
        } finally {
            @unlink($tmp);
        }
    }

    private function epochMs(mixed $ms): ?Carbon
    {
        $ms = $this->positive($ms);

        return $ms !== null ? Carbon::createFromTimestampMs($ms)->setTimezone(config('app.timezone')) : null;
    }

    private function positive(mixed $n): ?int
    {
        return is_numeric($n) && (int) $n > 0 ? (int) $n : null;
    }
}
