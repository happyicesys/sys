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
 * host. Each video gives its first and last frame and the rest over the part where something moves (moments()); camera 3
 * is cropped of its fisheye corners. Both are settings in smart_freezer.claude_recognition.
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
                $window = $this->motionWindow($tmp, $durationMs);
                $taken = $this->extract($tmp, $camera, $durationMs, self::moments($durationMs, (int) $config['frames_per_camera'], $window));
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
     * The first and last moment of the video (the baskets before and after) and the rest spread over the window where
     * something moves; with no window, an even spread. The last is a fifth of a second before the end, where a frame
     * still exists.
     *
     * @param  array{0: int, 1: int}|null  $window  [start, end] ms of the motion
     * @return list<int> milliseconds, ascending, no repeats
     */
    public static function moments(int $durationMs, int $count, ?array $window = null): array
    {
        $count = max(1, $count);
        $last = max(0, $durationMs - 200);
        if ($count === 1) {
            return [intdiv($last, 2)];
        }
        $spread = fn (int $from, int $to, int $n) => $n <= 1
            ? [intdiv($from + $to, 2)]
            : array_map(fn (int $i) => (int) round($from + ($to - $from) * $i / ($n - 1)), range(0, $n - 1));

        if ($window === null || $count < 3) {
            return $spread(0, $last, $count);
        }
        [$from, $to] = [max(0, min($window[0], $last)), max(0, min($window[1], $last))];
        $moments = array_merge([0], $spread($from, $to, $count - 2), [$last]);

        return array_values(array_unique($moments));
    }

    /**
     * Where something moves in the video: ffmpeg's scene-change score on a small 5 fps copy, above
     * `motion_threshold`, padded `motion_pad_ms` each side. Null when nothing moved or ffmpeg cannot say.
     *
     * @return array{0: int, 1: int}|null
     */
    private function motionWindow(string $video, int $durationMs): ?array
    {
        $bin = (string) config('smart_freezer.claude_recognition.ffmpeg_bin');
        $threshold = (float) config('smart_freezer.claude_recognition.motion_threshold');
        if ($bin === '' || ! is_executable($bin) || $threshold <= 0) {
            return null;
        }
        try {
            $process = new Process([$bin, '-hide_banner', '-nostats', '-i', $video, '-vf',
                "fps=5,scale=160:-2,select='gt(scene\\,{$threshold})',showinfo", '-f', 'null', '-']);
            $process->setTimeout(30);
            $process->run();
            preg_match_all('/pts_time:([0-9.]+)/', $process->getErrorOutput(), $m);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
        $times = array_map(fn ($t) => (int) round((float) $t * 1000), $m[1] ?? []);
        if ($times === []) {
            return null;
        }
        $pad = (int) config('smart_freezer.claude_recognition.motion_pad_ms');

        return [max(0, min($times) - $pad), min($durationMs, max($times) + $pad)];
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

    /**
     * @param  list<int>  $moments  ms
     * @return list<DoorFrame>
     */
    private function extract(string $video, int $camera, int $durationMs, array $moments): array
    {
        $bin = (string) config('smart_freezer.claude_recognition.ffmpeg_bin');
        if ($bin === '' || ! is_executable($bin)) {
            return [];
        }

        $height = max(144, (int) config('smart_freezer.claude_recognition.frame_height_px'));
        $crop = (float) (((array) config('smart_freezer.claude_recognition.crop_width'))[$camera] ?? 1);
        $filter = ($crop > 0 && $crop < 1 ? "crop=iw*{$crop}:ih," : '')."scale=-2:{$height}";

        $frames = [];
        foreach ($moments as $atMs) {
            $out = tempnam(sys_get_temp_dir(), 'sfframe').'.jpg';
            try {
                $process = new Process([
                    $bin, '-v', 'error', '-ss', sprintf('%.3f', $atMs / 1000), '-i', $video,
                    '-frames:v', '1', '-vf', $filter,
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
