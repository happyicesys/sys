<?php

namespace App\Services\SmartFreezer;

use App\Models\SmartFreezerVideo;
use App\Models\SmartFreezerVideoCopy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Keeps our own copy of each Zijia door-session video (Brian, 2026-11-09).
 *
 * Their files sit on Zijia's Alibaba OSS (Hangzhou) behind open links nobody agreed a lifetime for, and
 * they are the evidence behind every AI-judged charge. So each file a push names is downloaded once and
 * stored PRIVATE on our default disk (Spaces) under `freezer-videos/{vend}/zijia/`. The AI is still sent
 * their link; this copy is for us, and AI Recognition plays it when their link stops answering
 * ({@see playUrl}). Only writer of `smart_freezer_video_copies`.
 *
 * Fetches are limited to https on the configured hosts with redirects off, since the URL comes from a
 * request body; a file over `max_bytes` or not a video is refused.
 */
class ZijiaVideoCopier
{
    /**
     * Copies every file of $video not copied yet. Returns how many are now copied; a failure is
     * recorded on its row and rethrown when $throw, so a queued job retries.
     */
    public function copy(SmartFreezerVideo $video, bool $throw = false): int
    {
        $copied = 0;
        $failure = null;
        foreach ((array) $video->video_urls as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }
            try {
                $copied += $this->copyOne($video, $url) ? 1 : 0;
            } catch (Throwable $e) {
                $failure ??= $e;
            }
        }
        if ($failure !== null && $throw) {
            throw $failure;
        }

        return $copied;
    }

    /** True when $url is copied (now or before). */
    public function copyOne(SmartFreezerVideo $video, string $url): bool
    {
        $existing = SmartFreezerVideoCopy::where('url_hash', SmartFreezerVideoCopy::hashOf($url))->first();
        if ($existing?->isCopied()) {
            return true;
        }
        if (! $this->allowed($url)) {
            $this->record($video, $url, $existing, null, 'host not allowed for copying');

            return false;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'zjv');
        try {
            $response = Http::timeout((int) config('smart_freezer.zijia.video_copy.timeout'))
                ->withOptions(['sink' => $tmp, 'allow_redirects' => false])
                ->get($url);
            if (! $response->successful()) {
                throw new RuntimeException('their server answered HTTP '.$response->status());
            }
            $type = strtolower((string) $response->header('Content-Type'));
            if ($type !== '' && ! str_starts_with($type, 'video/') && ! str_starts_with($type, 'application/octet-stream')) {
                throw new RuntimeException("not a video ({$type})");
            }
            $bytes = (int) filesize($tmp);
            if ($bytes <= 0) {
                throw new RuntimeException('empty file');
            }
            if ($bytes > (int) config('smart_freezer.zijia.video_copy.max_bytes')) {
                throw new RuntimeException("too large ({$bytes} bytes)");
            }

            $path = $this->pathFor($video, $url);
            $stream = fopen($tmp, 'rb');
            try {
                Storage::writeStream($path, $stream, ['visibility' => 'private', 'mimetype' => 'video/mp4']);
            } finally {
                is_resource($stream) && fclose($stream);
            }
            $this->record($video, $url, $existing, [
                'status' => SmartFreezerVideoCopy::STATUS_COPIED,
                'path' => $path,
                'bytes' => $bytes,
                'sha256' => hash_file('sha256', $tmp),
                'duration_ms' => $this->durationMs($tmp),
                'error' => null,
                'copied_at' => Carbon::now(),
            ]);

            return true;
        } catch (Throwable $e) {
            $this->record($video, $url, $existing, null, mb_substr($e->getMessage(), 0, 255));
            Log::warning('zijia video copy failed', ['video' => $video->id, 'url' => $url, 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Fills in the length of a copy made before lengths were measured, from our stored file.
     * True when it now has one.
     */
    public function measure(SmartFreezerVideoCopy $copy): bool
    {
        if (! $copy->isCopied()) {
            return false;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'zjv');
        try {
            $in = Storage::readStream($copy->path);
            if (! is_resource($in)) {
                return false;
            }
            $out = fopen($tmp, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($out);
            fclose($in);
            $ms = $this->durationMs($tmp);
            if ($ms === null) {
                return false;
            }
            $copy->forceFill(['duration_ms' => $ms])->save();

            return true;
        } finally {
            @unlink($tmp);
        }
    }

    /** ffprobe's reading of the file's length, or null when ffprobe is missing or cannot read it. */
    public function durationMs(string $file): ?int
    {
        $bin = (string) config('smart_freezer.clips.ffprobe_bin');
        if ($bin === '' || ! is_executable($bin)) {
            return null;
        }
        try {
            $process = new Process([$bin, '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', $file]);
            $process->setTimeout(20);
            $process->run();
            $seconds = trim($process->getOutput());

            return $process->isSuccessful() && is_numeric($seconds) ? (int) round((float) $seconds * 1000) : null;
        } catch (Throwable $e) {
            Log::warning('zijia video ffprobe failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Where a viewer should be sent for $url: their link while it answers, else a short-lived signed
     * link to our copy, else null (gone everywhere). Their answer is cached a few minutes per URL.
     */
    public function playUrl(string $url): ?string
    {
        if ($this->theirsAnswers($url)) {
            return $url;
        }
        $copy = SmartFreezerVideoCopy::where('url_hash', SmartFreezerVideoCopy::hashOf($url))->first();
        if (! $copy?->isCopied()) {
            return null;
        }
        $disk = Storage::disk();
        if (! $disk->exists($copy->path)) {
            return null;
        }

        return $disk->providesTemporaryUrls()
            ? $disk->temporaryUrl($copy->path, now()->addMinutes((int) config('smart_freezer.clips.link_minutes')), ['ResponseContentType' => 'video/mp4'])
            : $disk->url($copy->path);
    }

    /** Whether their link still serves the file (HEAD, 200), cached per URL. */
    public function theirsAnswers(string $url): bool
    {
        $minutes = max(1, (int) config('smart_freezer.zijia.video_copy.probe_cache_minutes'));

        return Cache::remember('zijia-video-alive:'.SmartFreezerVideoCopy::hashOf($url), now()->addMinutes($minutes), function () use ($url) {
            if (! $this->allowed($url)) {
                return false;
            }
            try {
                return Http::timeout(5)->withOptions(['allow_redirects' => false])->head($url)->successful();
            } catch (Throwable) {
                return false;
            }
        });
    }

    /** https on a configured host: an exact name, or a subdomain of an entry starting with ".". */
    public function allowed(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        foreach ((array) config('smart_freezer.zijia.video_copy.hosts') as $allowed) {
            $allowed = strtolower((string) $allowed);
            if ($allowed === '') {
                continue;
            }
            if ($host === ltrim($allowed, '.') || (str_starts_with($allowed, '.') && str_ends_with($host, $allowed))) {
                return true;
            }
        }

        return false;
    }

    private function pathFor(SmartFreezerVideo $video, string $url): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) parse_url($url, PHP_URL_PATH))) ?: 'video.mp4';

        return sprintf('freezer-videos/%s/zijia/%d-%s-%s', $video->vend_id ?? 'unknown', $video->id, substr(SmartFreezerVideoCopy::hashOf($url), 0, 8), $name);
    }

    /** @param  array<string, mixed>|null  $copied */
    private function record(SmartFreezerVideo $video, string $url, ?SmartFreezerVideoCopy $existing, ?array $copied, ?string $error = null): void
    {
        $row = $existing ?? new SmartFreezerVideoCopy(['url_hash' => SmartFreezerVideoCopy::hashOf($url), 'url' => $url]);
        $row->fill(['smart_freezer_video_id' => $video->id, 'vend_id' => $video->vend_id]);
        $row->attempts = (int) $row->attempts + 1;
        if ($copied !== null) {
            $row->fill($copied);
        } else {
            $row->fill(['status' => SmartFreezerVideoCopy::STATUS_FAILED, 'error' => $error]);
        }
        $row->save();
    }
}
