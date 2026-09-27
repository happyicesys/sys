<?php

namespace App\Jobs;

use App\Models\SmartFreezerRecognition;
use App\Services\SmartFreezer\FreezerRecognitionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;

/**
 * Puts one freezer door session to Zijia's algorithm, off the webhook's request path.
 *
 * Waits for the session to go quiet first: when another camera's push arrived within the settle
 * window, the job re-queues itself for the rest of it, so the call carries every camera.
 *
 * One try, no retries: a recognition is metered on their side and the service records a failure
 * on the row, where a person decides whether to resubmit
 * (`smart-freezer:zijia-recognition <id> --retry`).
 */
class SubmitFreezerRecognition implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $recognitionId)
    {
        $this->onQueue('low');
    }

    public function handle(FreezerRecognitionService $recognitions): void
    {
        $recognition = SmartFreezerRecognition::find($this->recognitionId);
        if ($recognition === null || $recognition->status !== SmartFreezerRecognition::STATUS_PENDING) {
            return;
        }

        // Re-queue for the rest of the window — only on a queue that can delay. The sync driver runs
        // a "delayed" job at once, so re-queueing there would recurse forever; it submits instead.
        // (Not release(): that spends an attempt, and one try is all a metered call gets.)
        $wait = $recognitions->settleRemaining($recognition);
        if ($wait > 0 && $this->job !== null && ! $this->job instanceof SyncJob) {
            self::dispatch($recognition->id)->delay(now()->addSeconds($wait));

            return;
        }

        $recognitions->submit($recognition);
    }
}
