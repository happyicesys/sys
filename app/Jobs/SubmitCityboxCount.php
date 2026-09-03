<?php

namespace App\Jobs;

use App\Models\OpsJobItem;
use App\Services\Citybox\RestockVisitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push a Stocked-In chiller item's count to CityBox (design §6.1).
 *
 * Dispatched by CityboxOpsJobItemObserver with a short delay: the controller
 * flips the item to Stocked-In BEFORE it writes the per-channel actual_qty
 * rows, so an inline hook would read stale counts. Idempotent (bails if
 * already 'ok'), self-retrying with backoff up to MAX_SUBMIT_ATTEMPTS; a
 * final failure leaves status 'failed' + the reason for the amber banner.
 */
class SubmitCityboxCount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // we manage retries ourselves via re-dispatch, to keep the attempt count on the row

    /** $revert (Undo Stock In): push actual_before_qty back instead of before + stock-in. */
    public function __construct(public readonly int $opsJobItemId, public readonly bool $revert = false) {}

    public function handle(RestockVisitService $visits): void
    {
        $item = OpsJobItem::withoutGlobalScopes()->find($this->opsJobItemId);
        if (! $item) {
            return;
        }
        if ($this->revert) {
            $this->handleRevert($item, $visits);

            return;
        }
        if ($item->citybox_submit_status === 'ok') {
            return;
        }
        // Undo'd before we ran? Nothing to push.
        if ((int) $item->status < (int) \App\Models\OpsJob::STATUS_DELIVERED) {
            $item->forceFill(['citybox_submit_status' => null])->saveQuietly();

            return;
        }

        $ok = $visits->submitCount($item);
        $item->refresh();

        if (! $ok && $item->citybox_submit_attempts < RestockVisitService::MAX_SUBMIT_ATTEMPTS) {
            $delay = [30, 120, 300, 900][$item->citybox_submit_attempts - 1] ?? 900;
            Log::warning('Citybox count submit failed — retrying', ['ops_job_item_id' => $item->id, 'attempt' => $item->citybox_submit_attempts, 'retry_in_s' => $delay, 'error' => $item->citybox_submit_error]);
            self::dispatch($item->id)->delay(now()->addSeconds($delay))->onQueue('high');
        } elseif (! $ok) {
            Log::error('Citybox count submit gave up', ['ops_job_item_id' => $item->id, 'attempts' => $item->citybox_submit_attempts, 'error' => $item->citybox_submit_error]);
        }
    }

    private function handleRevert(OpsJobItem $item, RestockVisitService $visits): void
    {
        // Only a revert the controller armed; if the item was Stocked-In again
        // before we ran, the observer's fresh push supersedes this one.
        if ($item->citybox_submit_status !== 'reverting' || (int) $item->status >= (int) \App\Models\OpsJob::STATUS_DELIVERED) {
            return;
        }

        $ok = $visits->revertCount($item);
        $item->refresh();

        if (! $ok && $item->citybox_submit_attempts < RestockVisitService::MAX_SUBMIT_ATTEMPTS) {
            $delay = [30, 120, 300, 900][$item->citybox_submit_attempts - 1] ?? 900;
            $item->forceFill(['citybox_submit_status' => 'reverting'])->saveQuietly(); // keep it armed for the retry
            Log::warning('Citybox count revert failed — retrying', ['ops_job_item_id' => $item->id, 'attempt' => $item->citybox_submit_attempts, 'retry_in_s' => $delay, 'error' => $item->citybox_submit_error]);
            self::dispatch($item->id, true)->delay(now()->addSeconds($delay))->onQueue('high');
        } elseif (! $ok) {
            Log::error('Citybox count revert gave up', ['ops_job_item_id' => $item->id, 'attempts' => $item->citybox_submit_attempts, 'error' => $item->citybox_submit_error]);
        }
    }
}
