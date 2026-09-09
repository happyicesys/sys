<?php

namespace App\Services\Sales;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * The set of PAST days whose sales rollups must be rebuilt tonight.
 *
 * A TRADE that lands on a day before today (a machine replaying a month of
 * queued frames, or filling a gateway row that was marked 99), or a NETS
 * orphan row created from a report days later, changes a day the nightly
 * builders have already rolled up — and `reconcile:sales-rollups` only heals
 * days whose SUM(amount) drifted, which a counts-only change never trips.
 * So the ingest path records the DATE here (one O(1) SADD) and
 * `reconcile:sales-rollups --dirty` drains the set at 02:00: at most one
 * rebuild per touched day, none during trading hours.
 *
 * Redis, not Cache: the cache driver is file in prod and only the queue is
 * Redis. Every write is wrapped — a Redis blip may never fail a TRADE ingest;
 * the nightly amount-drift passes remain the safety net. A day is removed by
 * the LAST job of its rebuild chain (SREM, never SPOP), so a failed heal keeps
 * its date and is retried the next night.
 *
 * Container singleton (AppServiceProvider): a Horizon worker replaying a
 * thousand frames for the same few days sends each date once per
 * DEDUPE_SECONDS instead of once per frame. In tests (`SALES_DIRTY_DAYS_STORE=
 * array`) the singleton's own array is the set; a fresh app per test resets it.
 */
class DirtyDayRegistry
{
    /** A date already sent from this process is not re-sent for this long. */
    public const DEDUPE_SECONDS = 600;

    /** @var array<string, true> the set itself, in array mode (tests) */
    private array $memory = [];

    /** @var array<string, int> date → unix time of the last SADD from this process */
    private array $recentlySent = [];

    /** Record $day if it is before today. Never throws. */
    public function mark(CarbonInterface|string $day): void
    {
        $date = $day instanceof CarbonInterface ? $day->toDateString() : Carbon::parse($day)->toDateString();

        if ($date >= Carbon::now()->toDateString()) {
            return; // today is still in flight; the nightly builders take it
        }

        $t = time();
        if (($this->recentlySent[$date] ?? 0) > $t - self::DEDUPE_SECONDS) {
            return;
        }

        try {
            if ($this->usesMemory()) {
                $this->memory[$date] = true;
            } else {
                Redis::connection($this->connection())->sadd($this->key(), $date);
            }
            $this->recentlySent[$date] = $t;
        } catch (Throwable $e) {
            Log::warning('DirtyDayRegistry: could not record day; nightly drift reconcile will catch it.', [
                'day' => $date, 'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return string[] Y-m-d, ascending */
    public function days(): array
    {
        try {
            $days = $this->usesMemory()
                ? array_keys($this->memory)
                : (array) Redis::connection($this->connection())->smembers($this->key());
        } catch (Throwable $e) {
            Log::warning('DirtyDayRegistry: could not read days.', ['error' => $e->getMessage()]);

            return [];
        }

        sort($days);

        return array_values($days);
    }

    public function clear(string $day): void
    {
        unset($this->recentlySent[$day]);

        try {
            if ($this->usesMemory()) {
                unset($this->memory[$day]);
            } else {
                Redis::connection($this->connection())->srem($this->key(), $day);
            }
        } catch (Throwable $e) {
            Log::warning('DirtyDayRegistry: could not clear day.', ['day' => $day, 'error' => $e->getMessage()]);
        }
    }

    private function usesMemory(): bool
    {
        return config('sales.dirty_days_store') === 'array';
    }

    private function connection(): string
    {
        return (string) config('sales.dirty_days_connection');
    }

    private function key(): string
    {
        return (string) config('sales.dirty_days_key');
    }
}
