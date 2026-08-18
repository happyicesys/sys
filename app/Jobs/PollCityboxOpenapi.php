<?php

namespace App\Jobs;

use App\Services\Citybox\CityboxOpenapiSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * One CityBox-Openapi poll sweep (box_list + device_product per linked vend).
 * Dispatched every minute by citybox:openapi-poll. ShouldBeUnique stops
 * sweeps stacking when their API is slow. Fleet-level anomalies are logged
 * only when the set CHANGES — never every tick.
 */
class PollCityboxOpenapi implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 55;

    public function handle(CityboxOpenapiSync $sync): void
    {
        $summary = $sync->syncAll();

        $this->logOnChange('citybox:openapi:last_unknown', $summary['unknown'],
            fn ($ids) => Log::notice('Citybox openapi poll: unimported equipment seen', ['equipment_ids' => $ids]));

        $this->logOnChange('citybox:openapi:last_missing', $summary['missing'],
            fn ($ids) => Log::warning('Citybox openapi poll: linked vends missing from fleet list', ['equipment_ids' => $ids]));

        $this->logOnChange('citybox:openapi:last_stock_errors', array_keys($summary['stock_errors']),
            fn ($ids) => Log::warning('Citybox openapi poll: device_product failing', ['equipment_ids' => $ids, 'errors' => $summary['stock_errors']]));
    }

    private function logOnChange(string $cacheKey, array $ids, callable $log): void
    {
        sort($ids);
        $hash = md5(json_encode($ids));
        if (Cache::get($cacheKey) === $hash) {
            return;
        }
        Cache::put($cacheKey, $hash, now()->addWeek());
        if ($ids) {
            $log($ids);
        }
    }
}
