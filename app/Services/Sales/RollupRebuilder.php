<?php

namespace App\Services\Sales;

use App\Jobs\ProcessGpMetricsDay;
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
 * the caller hang a tail on completion (the dirty-day clear) that only runs if
 * the rebuild actually succeeded.
 */
class RollupRebuilder
{
    public const DEFAULT_QUEUE = 'low';

    public const DEFAULT_CHUNK = 1000;

    /**
     * @param  string[]  $days  Y-m-d
     * @param  ?callable  $afterEach  tail appended to each day's chain; must be serialisable
     *                                (capture the date string, never a service instance)
     * @return int days dispatched
     */
    public function dispatchDays(array $days, string $queue = self::DEFAULT_QUEUE, int $chunk = self::DEFAULT_CHUNK, ?callable $afterEach = null): int
    {
        $days = array_values(array_unique(array_filter($days)));

        foreach ($days as $day) {
            $chain = $this->jobsFor($day, $chunk);
            if ($afterEach) {
                $chain[] = $afterEach($day);
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
