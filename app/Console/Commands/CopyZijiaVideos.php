<?php

namespace App\Console\Commands;

use App\Models\SmartFreezerVideo;
use App\Models\SmartFreezerVideoCopy;
use App\Services\SmartFreezer\ZijiaVideoCopier;
use Illuminate\Console\Command;
use Throwable;

/**
 * Copies Zijia door-session videos we do not hold yet (the pushes from before copying began, or one
 * whose copy kept failing), and measures the length of copies made before lengths were kept. Dry run by
 * default: lists what it would fetch.
 */
class CopyZijiaVideos extends Command
{
    protected $signature = 'smart-freezer:copy-zijia-videos {--days=90 : pushes received in the last N days} {--apply : download and store}';

    protected $description = 'Keep our own copy (Spaces, private) of every Zijia door-session video';

    public function handle(ZijiaVideoCopier $copier): int
    {
        $missing = 0;
        $done = 0;
        $failed = 0;
        SmartFreezerVideo::query()
            ->where('created_at', '>=', now()->subDays((int) $this->option('days')))
            ->orderBy('id')
            ->each(function (SmartFreezerVideo $video) use ($copier, &$missing, &$done, &$failed) {
                foreach ((array) $video->video_urls as $url) {
                    if (! is_string($url) || SmartFreezerVideoCopy::where('url_hash', SmartFreezerVideoCopy::hashOf($url))->where('status', SmartFreezerVideoCopy::STATUS_COPIED)->exists()) {
                        continue;
                    }
                    $missing++;
                    if (! $this->option('apply')) {
                        $this->line("would copy video {$video->id}: {$url}");

                        continue;
                    }
                    try {
                        $copier->copyOne($video, $url) ? $done++ : $failed++;
                    } catch (Throwable $e) {
                        $failed++;
                        $this->warn("video {$video->id}: ".$e->getMessage());
                    }
                }
            });

        $this->info($this->option('apply')
            ? "copied {$done}, failed {$failed}, of {$missing} missing"
            : "{$missing} file(s) to copy — run with --apply");

        // Copies made before lengths were measured (2026-11-09): read the length off our stored file.
        $unmeasured = SmartFreezerVideoCopy::where('status', SmartFreezerVideoCopy::STATUS_COPIED)
            ->whereNull('duration_ms')
            ->where('created_at', '>=', now()->subDays((int) $this->option('days')));
        if (! $this->option('apply')) {
            $this->info($unmeasured->count().' copied file(s) with no length yet — run with --apply');

            return self::SUCCESS;
        }
        $measured = 0;
        $unmeasured->orderBy('id')->each(function (SmartFreezerVideoCopy $copy) use ($copier, &$measured) {
            try {
                $measured += $copier->measure($copy) ? 1 : 0;
            } catch (Throwable $e) {
                $this->warn("copy {$copy->id}: ".$e->getMessage());
            }
        });
        $this->info("measured {$measured} file(s)");

        return self::SUCCESS;
    }
}
