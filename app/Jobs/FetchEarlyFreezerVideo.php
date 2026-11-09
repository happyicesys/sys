<?php

namespace App\Jobs;

use App\Services\SmartFreezer\Zijia\EarlyOrderVideo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;

/**
 * One look on Zijia's storage for a door session's order video (EarlyOrderVideo). Not there yet →
 * queues the next look `retry_seconds` out, until the give-up time; then Zijia's push takes over as
 * before. Costs nothing on their side: a HEAD on a public file. On the `default` queue, because
 * `low` also draws welcome sketches, which can hold it for minutes.
 */
class FetchEarlyFreezerVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly int $vendId,
        public readonly string $orderRef,
        public readonly int $giveUpAt,
    ) {
        $this->onQueue('default');
    }

    public function handle(EarlyOrderVideo $early): void
    {
        $outcome = $early->fetch($this->vendId, $this->orderRef, $this->giveUpAt);

        // The sync driver cannot wait: looking again there would spin until the give-up time.
        if ($outcome === 'waiting' && $this->job !== null && ! $this->job instanceof SyncJob) {
            self::dispatch($this->vendId, $this->orderRef, $this->giveUpAt)
                ->delay(now()->addSeconds(max(1, (int) config('smart_freezer.zijia.early_video.retry_seconds'))));
        }
    }
}
