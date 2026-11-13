<?php

namespace App\Services\SmartFreezer\Claude;

use App\Models\SmartFreezerRecognition;
use App\Models\SmartFreezerVideo;
use App\Models\SmartFreezerVideoCopy;
use App\Services\SmartFreezer\FreezerRecognitionService;
use App\Services\SmartFreezer\ZijiaVideoCopier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Frames from the same videos Zijia's AI is sent (FreezerRecognitionService::sendableVideos), cameras in
 * `smart_freezer.claude_recognition.cameras` only — the camera is read from Zijia's file name ("-d1c3-" =
 * door 1, camera 3), and a file whose camera cannot be read is left out, so the customer-facing camera is
 * never sent by mistake. Our own Spaces copy is read first (ZijiaVideoCopier), else their link on an allowed
 * host. Frames are spread evenly from the first to the last moment of each video, both ends included.
 */
class FfmpegDoorFrames implements DoorFrameSource
{
    public function __construct(private readonly ZijiaVideoCopier $copier) {}

    public function framesFor(SmartFreezerRecognition $recognition): array
    {
        $config = config('smart_freezer.claude_recognition');
        $wanted = array_map('intval', (array) $config['cameras']);
        $frames = [];
        $notes = [];
        $seen = [];

        $urls = FreezerRecognitionService::sendableVideos($recognition->videos)
            ->flatMap(fn (SmartFreezerVideo $v) => (array) $v->video_urls)
            ->filter(fn ($u) => is_string($u) && $u !== '')
            ->unique()->values();

        foreach ($urls as $url) {
            $camera = self::cameraOf($url);
            if ($camera === null || ! in_array($camera, $wanted, true)) {
                $notes[] = 'left out '.basename((string) parse_url($url, PHP_URL_PATH)).($camera === null ? ' (camera not named)' : " (camera {$camera})");

                continue;
            }
            if (isset($seen[$camera])) {
                $notes[] = "a second camera {$camera} video was left out";

                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'sfclaude');
            try {
                if (! $this->fetch($url, $tmp)) {
                    $notes[] = "camera {$camera}: video could not be read";

                    continue;
                }
                $durationMs = $this->copier->durationMs($tmp);
                if ($durationMs === null || $durationMs <= 0) {
                    $notes[] = "camera {$camera}: video length could not be read";

                    continue;
                }
                $taken = $this->extract($tmp, $camera, $durationMs, (int) $config['frames_per_camera'], (int) $config['frame_max_px']);
                if ($taken === []) {
                    $notes[] = "camera {$camera}: no frame could be cut";

                    continue;
                }
                $seen[$camera] = true;
                array_push($frames, ...$taken);
            } finally {
                @unlink($tmp);
            }
        }

        usort($frames, fn (DoorFrame $a, DoorFrame $b) => [$a->camera, $a->atMs] <=> [$b->camera, $b->atMs]);

        return ['frames' => $frames, 'notes' => $notes];
    }

    /** "…/SF-50001-…-d1c3-25f-1280x720.mp4" → 3; null when the name carries no camera. */
    public static function cameraOf(string $url): ?int
    {
        $name = basename((string) parse_url($url, PHP_URL_PATH));

        return preg_match('/-d\d+c(\d+)-/i', $name, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * Even spread over the video, both ends included; the last a fifth of a second before the end, where a
     * frame still exists.
     *
     * @return list<int> milliseconds
     */
    public static function moments(int $durationMs, int $count): array
    {
        $count = max(1, $count);
        $last = max(0, $durationMs - 200);
        if ($count === 1) {
            return [intdiv($last, 2)];
        }

        return array_map(fn (int $i) => (int) round($last * $i / ($count - 1)), range(0, $count - 1));
    }

    private function fetch(string $url, string $tmp): bool
    {
        $max = (int) config('smart_freezer.claude_recognition.max_video_bytes');
        $copy = SmartFreezerVideoCopy::where('url_hash', SmartFreezerVideoCopy::hashOf($url))->first();
        if ($copy?->isCopied()) {
            try {
                $in = Storage::readStream($copy->path);
                if ($in !== null) {
                    $out = fopen($tmp, 'wb');
                    stream_copy_to_stream($in, $out, $max + 1);
                    fclose($out);
                    is_resource($in) && fclose($in);
                    if (filesize($tmp) > 0 && filesize($tmp) <= $max) {
                        return true;
                    }
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        if (! $this->copier->allowed($url)) {
            return false;
        }
        try {
            $response = Http::timeout(60)->withOptions(['allow_redirects' => false, 'sink' => $tmp])->get($url);
            clearstatcache(true, $tmp);
            if ($response->successful() && filesize($tmp) === 0 && $response->body() !== '') {
                // A client that kept the body in memory instead of the sink (the HTTP fake does).
                file_put_contents($tmp, $response->body());
                clearstatcache(true, $tmp);
            }

            return $response->successful() && filesize($tmp) > 0 && filesize($tmp) <= $max;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<DoorFrame> */
    private function extract(string $video, int $camera, int $durationMs, int $count, int $maxPx): array
    {
        $bin = (string) config('smart_freezer.claude_recognition.ffmpeg_bin');
        if ($bin === '' || ! is_executable($bin)) {
            return [];
        }

        $frames = [];
        foreach (self::moments($durationMs, $count) as $atMs) {
            $out = tempnam(sys_get_temp_dir(), 'sfframe').'.jpg';
            try {
                $process = new Process([
                    $bin, '-v', 'error', '-ss', sprintf('%.3f', $atMs / 1000), '-i', $video,
                    '-frames:v', '1', '-vf', "scale=w={$maxPx}:h={$maxPx}:force_original_aspect_ratio=decrease",
                    '-q:v', '4', '-f', 'image2', '-y', $out,
                ]);
                $process->setTimeout(30);
                $process->run();
                if ($process->isSuccessful() && is_file($out) && filesize($out) > 0) {
                    $frames[] = new DoorFrame($camera, $atMs, $durationMs, (string) file_get_contents($out));
                }
            } catch (Throwable $e) {
                report($e);
            } finally {
                @unlink($out);
                @unlink(substr($out, 0, -4));
            }
        }

        return $frames;
    }
}
