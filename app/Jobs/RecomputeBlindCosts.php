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
