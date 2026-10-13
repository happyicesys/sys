<?php

namespace App\Jobs;

use App\Services\Citybox\CityboxOrderSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * One pull of CityBox orders over a list of ORDER days (citybox:pull-orders). Unique per window,
 * so a slow API never stacks the minute pull; the per-day lock in CityboxOrderSync keeps the
 * minute / hourly / nightly windows off each other's days. A day that fails is logged only when
 * the set of failing days CHANGES — their API blips a few times a day.
 */
class PullCityboxOrders implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    /** @param list<string> $dates */
    public function __construct(public array $dates) {}

    public function uniqueId(): string
    {
        return implode(',', [reset($this->dates), end($this->dates), count($this->dates)]);
    }

    public function handle(CityboxOrderSync $sync): void
    {
        $stats = $sync->syncDays($this->dates);

        $failed = array_keys($stats['failed_days']);
        sort($failed);
        $key = 'citybox:orders:failed:'.$this->uniqueId();
        if (Cache::get($key) !== $failed) {
            Cache::put($key, $failed, now()->addDay());
            if ($failed !== []) {
                Log::warning('Citybox order pull: days failed', ['errors' => $stats['failed_days']]);
            }
        }
        if ($stats['changed'] || $stats['order_errors']) {
            Log::info('Citybox order pull', $stats);
        }
    }
}
