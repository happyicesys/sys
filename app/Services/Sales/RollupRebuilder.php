<?php

namespace App\Services\Sales;

use App\Jobs\ProcessGpMetricsDay;
use App\Jobs\Sales\ClearDirtyDay;
use App\Jobs\StoreVendProductRecords;
use App\Jobs\StoreVendsRecord;
use Illuminate\Support\Facades\Bus;

/**
 * "Rebuild the day-level sales rollups for these days" — ONE recipe, so every
 * caller queues the same three jobs in the same order.
 *
 * Callers: `reconcile:sales-rollups` (both the amount-drift pass and the
 * --dirty pass) and CardSettlementSyncService, which rebuilds the days a NETS
 * report just changed instead of leaving dashboards stale until 02:00.
 *
 * Chained, not fanned out: the three write different tables but all read the
 * same day, `vend_records` has no unique key of its own, and a chain also lets
 * the caller hang a tail on completion (ClearDirtyDay) that only runs if the
 * rebuild actually succeeded. That tail is a JOB, never a queued closure — see
 * the note on ClearDirtyDay for what closures did here in production.
 */
class RollupRebuilder
{
    public const DEFAULT_QUEUE = 'low';

    public const DEFAULT_CHUNK = 1000;

    /**
     * @param  string[]  $days  Y-m-d
     * @param  bool  $clearDirtyDay  append ClearDirtyDay, so a day leaves the dirty
     *                               set only once its rebuilds actually succeeded
     * @return int days dispatched
     */
    public function dispatchDays(array $days, string $queue = self::DEFAULT_QUEUE, int $chunk = self::DEFAULT_CHUNK, bool $clearDirtyDay = false): int
    {
        $days = array_values(array_unique(array_filter($days)));

        foreach ($days as $day) {
            $chain = $this->jobsFor($day, $chunk);
            if ($clearDirtyDay) {
                $chain[] = new ClearDirtyDay($day);
            }
            Bus::chain($chain)->onQueue($queue)->dispatch();
        }

        return count($days);
    }

    /** @return array<int, object> the three day-level rollups, in the order they run */
    public function jobsFor(string $day, int $chunk = self::DEFAULT_CHUNK): array
    {
        return [
            new StoreVendsRecord($day, $day, true),
            new ProcessGpMetricsDay($day, $chunk),
            new StoreVendProductRecords($day, $day),
        ];
    }
}
