<?php

namespace App\Jobs;

use App\Services\Citybox\CityboxEquipmentSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * One Citybox poll sweep. Dispatched every minute by citybox:poll (which owns
 * the "any chillers linked at all?" guard). ShouldBeUnique stops sweeps from
 * stacking in the queue when their API responds slower than our interval.
 *
 * This runs 1,440×/day, so fleet-list anomalies (unknown / missing devices)
 * are logged only when the set CHANGES — never repeated every tick. Per-vend
 * signals (stock deltas, status transitions) are change-gated inside the sync
 * service for the same reason.
 */
class PollCityboxEquipment implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 55;

    public function handle(CityboxEquipmentSync $sync): void
    {
        $summary = $sync->syncAll();

        // Their fleet has devices we haven't imported — import-screen backlog,
        // never auto-created. Surface once per change of the set.
        $this->logOnChange(
            'citybox:poll:last_unknown',
            $summary['unknown'],
            fn ($ids) => Log::notice('Citybox poll: unimported equipment seen', ['equipment_ids' => $ids]),
        );

        // Our linked vends absent from their fleet list — likely removed on
        // their side; needs an ops look.
        $this->logOnChange(
            'citybox:poll:last_missing',
            $summary['missing'],
            fn ($ids) => Log::warning('Citybox poll: linked vends missing from supplier fleet list', ['equipment_ids' => $ids]),
        );
    }

    /** Fire $log only when $ids differs from the set seen on the previous poll. */
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
