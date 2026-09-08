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
 * the nightly amount-drift passes remain the safety net. Days are cleared one
 * by one AFTER their heal is dispatched (SREM, never SPOP), so a failed heal
 * keeps its date.
 */
class DirtyDayRegistry
{
    /** @var array<string, true> in-process store for tests */
    private static array $memory = [];

    /** Record $day if it is before today. Never throws. */
    public function mark(CarbonInterface|string $day, ?CarbonInterface $now = null): void
    {
        $now ??= Carbon::now();
        $date = $day instanceof CarbonInterface ? $day->toDateString() : Carbon::parse($day)->toDateString();

        if ($date >= $now->toDateString()) {
            return; // today is still in flight; the nightly builders take it
        }

        try {
            if ($this->usesMemory()) {
                self::$memory[$date] = true;

                return;
            }
            Redis::connection($this->connection())->sadd($this->key(), $date);
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
                ? array_keys(self::$memory)
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
        try {
            if ($this->usesMemory()) {
                unset(self::$memory[$day]);

                return;
            }
            Redis::connection($this->connection())->srem($this->key(), $day);
        } catch (Throwable $e) {
            Log::warning('DirtyDayRegistry: could not clear day.', ['day' => $day, 'error' => $e->getMessage()]);
        }
    }

    /** Tests only. */
    public static function flushMemory(): void
    {
        self::$memory = [];
    }

    private function usesMemory(): bool
    {
        return config('sales.dirty_days_store') === 'array';
    }

    private function connection(): string
    {
        return (string) config('sales.dirty_days_connection', 'default');
    }

    private function key(): string
    {
        return (string) config('sales.dirty_days_key', 'sales:rollups:dirty-days');
    }
}
