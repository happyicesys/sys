<?php

namespace App\Observers;

use App\Models\OpsJobItem;
use App\Models\OpsJobItemFreezeQueue;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the freeze work-queue (ops_job_item_freeze_queue) in sync with each
 * ops job item's state, so the every-minute ops:freeze-stock-in command only
 * ever scans the small set of items actually waiting to freeze — instead of the
 * whole ops_job_items history.
 *
 * Enqueue when an item becomes freeze-eligible (delivered+, not cancelled,
 * stocked-in, not yet frozen); dequeue when it stops being eligible (undo,
 * cancel, or once frozen). eligible_at = completed_at + 10 min, matching the
 * original 10-minute freeze delay.
 *
 * Deliberately cheap: it early-returns unless one of the three columns that
 * affect eligibility actually changed, so the vast majority of ops job item
 * saves do zero extra work.
 */
class OpsJobItemObserver
{
    public function saved(OpsJobItem $item): void
    {
        // Only the status / completed_at / frozen_at transitions can change
        // freeze eligibility. Skip every other save (qty edits, cash, notes...).
        if (! $item->wasRecentlyCreated
            && ! $item->wasChanged(['status', 'completed_at', 'frozen_at'])) {
            return;
        }

        // The queue is only a performance optimization for the freeze command;
        // it must never break a stock-in save. Any failure here (incl. the table
        // not existing yet during a deploy window) is swallowed — the hourly
        // ops:freeze-queue-reconcile sweep re-enqueues anything missed.
        try {
            $this->sync($item);
        } catch (\Throwable $e) {
            Log::warning('OpsJobItemObserver freeze-queue sync failed for ops_job_item ' . $item->id . ': ' . $e->getMessage());
        }
    }

    public function deleted(OpsJobItem $item): void
    {
        try {
            OpsJobItemFreezeQueue::where('ops_job_item_id', $item->id)->delete();
        } catch (\Throwable $e) {
            Log::warning('OpsJobItemObserver freeze-queue cleanup failed for ops_job_item ' . $item->id . ': ' . $e->getMessage());
        }
    }

    private function sync(OpsJobItem $item): void
    {
        if ($item->isFreezeEligible()) {
            OpsJobItemFreezeQueue::updateOrCreate(
                ['ops_job_item_id' => $item->id],
                ['eligible_at' => $item->completed_at->copy()->addMinutes(10)]
            );

            return;
        }

        OpsJobItemFreezeQueue::where('ops_job_item_id', $item->id)->delete();
    }
}
