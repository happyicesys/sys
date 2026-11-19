<?php

namespace App\Jobs;

use App\Models\SmartFreezerVideo;
use App\Services\SmartFreezer\OwnAi\OwnAiLink;
use App\Services\SmartFreezer\ZijiaVideoCopier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Copies one Zijia video push's files into our Spaces ({@see ZijiaVideoCopier}). Queued from the video
 * webhook so the push is answered at once; retried with backoff, since their OSS or our link may blip.
 */
class CopyZijiaVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 600;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 1800];
    }

    public function __construct(public int $videoId)
    {
        $this->onQueue('low');
    }

    public function handle(ZijiaVideoCopier $copier, OwnAiLink $ownAi): void
    {
        $video = SmartFreezerVideo::find($this->videoId);
        if ($video !== null) {
            try {
                $copier->copy($video, throw: true);
            } finally {
                // Our own AI is told the moment its camera-3 copy is in (one camera may copy while the other fails).
                $ownAi->videosCopied($video);
            }
        }
    }
}
