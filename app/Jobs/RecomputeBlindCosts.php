<?php

namespace App\Jobs;

use App\Services\BlindSkuService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Recompute per-mapping blended unit costs off the request path.
 *
 * Dispatched either for a child product (fan out to every parent slot that uses
 * it) or for an explicit set of parent slot ids.
 */
class RecomputeBlindCosts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Each parent is written in its own transaction and a recompute of an unchanged
    // cost is a no-op, so a retry is safe. One deadlock on unit_costs (2026-11-09
    // 00:05) failed the job outright under Horizon's tries = 1.
    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(
        public ?int $childProductId = null,
        public array $parentIds = [],
    ) {}

    public function handle(): void
    {
        if ($this->childProductId !== null) {
            BlindSkuService::recomputeForChildProduct($this->childProductId);
        }
        if (!empty($this->parentIds)) {
            BlindSkuService::recomputeParents($this->parentIds);
        }
    }
}
