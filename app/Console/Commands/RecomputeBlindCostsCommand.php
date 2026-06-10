<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\BlindSkuService;
use Illuminate\Console\Command;

/**
 * Nightly backstop: re-blend every parent slot so the per-mapping blended unit
 * costs stay correct even if an observer was missed (queue failure, bulk import,
 * direct DB edit). Idempotent — recomputeForParent only writes when the blend changed.
 */
class RecomputeBlindCostsCommand extends Command
{
    protected $signature = 'blind:recompute-costs';

    protected $description = 'Recompute per-mapping blended unit costs for all blind parent slots';

    public function handle(): int
    {
        $parentIds = Product::withoutGlobalScopes()
            ->where('is_parent_sku', true)
            ->pluck('id')
            ->all();

        if (empty($parentIds)) {
            $this->info('No blind parent SKUs. Nothing to do.');
            return self::SUCCESS;
        }

        $tally = BlindSkuService::recomputeParents($parentIds);

        $this->info(sprintf(
            'Blind cost recompute: %d updated, %d unchanged, %d incomplete (missing child cost).',
            $tally['updated'], $tally['unchanged'], $tally['incomplete']
        ));

        return self::SUCCESS;
    }
}
