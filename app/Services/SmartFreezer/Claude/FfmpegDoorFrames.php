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

    public function framesFor(SmartFreezerRecognition $recognition, ?int $framesPerSession = null, bool $sheets = true): array
    {
        $config = config('smart_freezer.claude_recognition');
        $wanted = array_map('intval', (array) $config['cameras']);
        $frames = [];
        $notes = [];

        $urls = FreezerRecognitionService::sendableVideos($recognition->videos)
            ->flatMap(fn (SmartFreezerVideo $v) => (array) $v->video_urls)
            ->filter(fn ($u) => is_string($u) && $u !== '')
            ->unique()->values();

        // Fetch every usable camera first: the frame budget is per SESSION and split over the cameras that have a
        // video, so a session filmed by camera 3 alone (50001 while its camera 4 is offline) gets every frame on it
        // instead of half (Brian, 2026-11-13: session #138 — 12 frames 2 s apart missed two of four items).
        $videos = [];
        try {
            foreach ($urls as $url) {
                $camera = self::cameraOf($url);
                if ($camera === null || ! in_array($camera, $wanted, true)) {
                    $notes[] = 'left out '.basename((string) parse_url($url, PHP_URL_PATH)).($camera === null ? ' (camera not named)' : " (camera {$camera})");

                    continue;
                }
                if (isset($videos[$camera])) {
                    $notes[] = "a second camera {$camera} video was left out";

                    continue;
                }
                $tmp = tempnam(sys_get_temp_dir(), 'sfclaude');
                if (! $this->fetch($url, $tmp)) {
                    @unlink($tmp);
                    $notes[] = "camera {$camera}: video could not be read";

                    continue;
                }
                $durationMs = $this->copier->durationMs($tmp);
                if ($durationMs === null || $durationMs <= 0) {
                    @unlink($tmp);
                    $notes[] = "camera {$camera}: video length could not be read";

                    continue;
                }
                $videos[$camera] = [$tmp, $durationMs];
            }

            $each = self::framesPerCamera($framesPerSession ?? (int) $config['frames_per_session'], count($videos));
            foreach ($videos as $camera => [$tmp, $durationMs]) {
                $window = $this->motionWindow($tmp, $durationMs);
                if ($sheets && (bool) ($config['sheets']['enabled'] ?? false)) {
                    array_push($frames, ...$this->sheets($tmp, $camera, $durationMs, $window));
                }
                $taken = $this->extract($tmp, $camera, $durationMs, self::moments($durationMs, $each, $window), $this->frameFilter($camera));
                if ($taken === []) {
                    $notes[] = "camera {$camera}: no frame could be cut";

                    continue;
                }
                array_push($frames, ...$taken);
                // The baskets before and after at full detail (Brian, 2026-11-13: two identical bars lifted together
                // read as one at 576 px). Counting the gap in a section is easier than following a hand.
                if ((bool) ($config['closeups']['enabled'] ?? false)) {
                    $last = max(0, $durationMs - 200);
                    array_push($frames, ...$this->extract($tmp, $camera, $durationMs, [0], $this->closeUpFilter($camera), DoorFrame::KIND_BEFORE));
                    array_push($frames, ...$this->extract($tmp, $camera, $durationMs, [$last], $this->closeUpFilter($camera), DoorFrame::KIND_AFTER));
                }
            }
        } finally {
            foreach ($videos as [$tmp]) {
                @unlink($tmp);
            }
        }

        // Camera, then the motion frames in time order, then that camera's before and after close-ups; the contact
        // sheets of every camera last (the order the 2026-11-13 tuning measured).
        $rank = [DoorFrame::KIND_FRAME => 0, DoorFrame::KIND_BEFORE => 1, DoorFrame::KIND_AFTER => 2, DoorFrame::KIND_SHEET => 3];
        usort($frames, fn (DoorFrame $a, DoorFrame $b) => [$a->kind === DoorFrame::KIND_SHEET, $a->camera, $rank[$a->kind], $a->atMs]
            <=> [$b->kind === DoorFrame::KIND_SHEET, $b->camera, $rank[$b->kind], $b->atMs]);

        return ['frames' => $frames, 'notes' => $notes];
    }

    /** The whole picture, `frame_height_px` high, camera 3's black fisheye corners cropped (`crop_width`). */
    private function frameFilter(int $camera): string
    {
        $height = max(144, (int) config('smart_freezer.claude_recognition.frame_height_px'));
        $crop = (float) (((array) config('smart_freezer.claude_recognition.crop_width'))[$camera] ?? 1);

        return ($crop > 0 && $crop < 1 ? "crop=iw*{$crop}:ih," : '')."scale=-2:{$height}";
    }

    /**
     * The basket area only (`closeups.box` per camera, as shares of the picture: left, top, right, bottom; a camera
     * with no box keeps the whole picture), enlarged to `closeups.width_px` so a small pack covers more of what
     * Claude reads. Enlarging adds no detail the camera did not record; it only stops the picture being shrunk.
     */
    public static function closeUpFilter(int $camera): string
    {
        $config = (array) config('smart_freezer.claude_recognition.closeups');
        $width = max(320, (int) ($config['width_px'] ?? 1568));
        $box = ((array) ($config['box'] ?? []))[$camera] ?? null;
        $crop = '';
        if (is_array($box) && count($box) === 4) {
            [$left, $top, $right, $bottom] = array_map('floatval', $box);
            if ($left >= 0 && $top >= 0 && $right <= 1 && $bottom <= 1 && $right > $left && $bottom > $top) {
                $crop = sprintf('crop=iw*%.4F:ih*%.4F:iw*%.4F:ih*%.4F,', $right - $left, $bottom - $top, $left, $top);
            }
        }

        return $crop."scale={$width}:-2:flags=lanczos";
    }

    /**
     * Contact sheets over the motion window (padded `sheets.pad_ms`): `cols` x `rows` small pictures `fps` a second,
     * cropped to the baskets and the space above them, one image per sheet, at most `max` sheets (the rate drops to
     * fit). A reach lasts about half a second (#54, 2026-11-13: two reaches, at 5.0 s and 6.5 s), so stills a second
     * apart miss one; the tuning set went 13 -> 14 of 20 with them, for about US$0.02 a session.
     *
     * @param  array{0: int, 1: int}|null  $window
     * @return list<DoorFrame>
     */
    private function sheets(string $video, int $camera, int $durationMs, ?array $window): array
    {
        $bin = (string) config('smart_freezer.claude_recognition.ffmpeg_bin');
        $config = (array) config('smart_freezer.claude_recognition.sheets');
        if ($bin === '' || ! is_executable($bin)) {
            return [];
        }
        $pad = (int) ($config['pad_ms'] ?? 600);
        [$from, $to] = $window === null ? [0, $durationMs] : [max(0, $window[0] - $pad), min($durationMs, $window[1] + $pad)];
        $cols = max(1, (int) ($config['cols'] ?? 3));
        $rows = max(1, (int) ($config['rows'] ?? 3));
        $per = $cols * $rows;
        $fps = max(0.5, (float) ($config['fps'] ?? 3));
        $max = max(1, (int) ($config['max'] ?? 6));
        $seconds = max(0.5, ($to - $from) / 1000);
        if ((int) floor($seconds * $fps) + 1 > $per * $max) {
            $fps = ($per * $max - 1) / $seconds;
        }
        $stepMs = (int) round(1000 / $fps);
        $box = ((array) ($config['box'] ?? []))[$camera] ?? null;
        $crop = is_array($box) && count($box) === 4
            ? sprintf('crop=iw*%.4F:ih*%.4F:iw*%.4F:ih*%.4F,', $box[2] - $box[0], $box[3] - $box[1], $box[0], $box[1])
            : '';
        $tileWidth = intdiv(max(320, (int) ($config['width_px'] ?? 1568)), $cols);

        $sheets = [];
        for ($start = $from; $start <= $to && count($sheets) < $max; $start += $per * $stepMs) {
            $out = tempnam(sys_get_temp_dir(), 'sfsheet').'.jpg';
            try {
                $process = new Process([$bin, '-v', 'error', '-ss', sprintf('%.3f', $start / 1000), '-i', $video,
                    '-t', sprintf('%.3f', $per * $stepMs / 1000),
                    '-vf', sprintf('fps=%.4F,%sscale=%d:-2,tile=%dx%d', 1000 / $stepMs, $crop, $tileWidth, $cols, $rows),
                    '-frames:v', '1', '-q:v', '3', '-f', 'image2', '-y', $out]);
                $process->setTimeout(60);
                $process->run();
                if ($process->isSuccessful() && is_file($out) && filesize($out) > 0) {
                    $sheets[] = new DoorFrame($camera, $start, $durationMs, (string) file_get_contents($out), DoorFrame::KIND_SHEET,
                        ['to_ms' => (int) min($to, $start + ($per - 1) * $stepMs), 'step_ms' => $stepMs, 'cols' => $cols, 'rows' => $rows]);
                }
            } catch (Throwable $e) {
                report($e);
            } finally {
                @unlink($out);
                @unlink(substr($out, 0, -4));
            }
        }

        return $sheets;
    }

    /** The session's frame budget split over the cameras that have a video, at least 2 each (before and after). */
    public static function framesPerCamera(int $perSession, int $cameras): int
    {
        return $cameras <= 0 ? 0 : max(2, intdiv(max(2, $perSession), $cameras));
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
    private function extract(string $video, int $camera, int $durationMs, array $moments, string $filter, string $kind = DoorFrame::KIND_FRAME): array
    {
        $bin = (string) config('smart_freezer.claude_recognition.ffmpeg_bin');
        if ($bin === '' || ! is_executable($bin)) {
            return [];
        }

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
                    $frames[] = new DoorFrame($camera, $atMs, $durationMs, (string) file_get_contents($out), $kind);
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
