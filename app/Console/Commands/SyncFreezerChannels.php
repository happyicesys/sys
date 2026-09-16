<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Services\Freezer\FreezerChannelSync;
use Illuminate\Console\Command;

/**
 * Writes every Smart Freezer's `vend_channels` from its planogram.
 *
 * Needed once per freezer that was bound before FreezerChannelSync existed (their channel cell on
 * the Operation Dashboard is blank until then); after that the sync runs itself whenever a mapping
 * or a Site's RP changes. Idempotent: re-running keeps each slot's qty and capacity.
 */
class SyncFreezerChannels extends Command
{
    protected $signature = 'freezer:sync-channels {--codes= : Only these machine codes, comma separated} {--apply : Write; otherwise list what would be written}';

    protected $description = "Write smart freezers' vend_channels from their planogram";

    public function handle(FreezerChannelSync $sync): int
    {
        $query = Vend::withoutGlobalScopes()->where('machine_type', Vend::MACHINE_TYPE_SMART_FREEZER);
        if ($codes = $this->option('codes')) {
            $query->whereIn('code', array_filter(array_map('trim', explode(',', $codes))));
        }

        $vends = $query->orderBy('code')->get();
        if ($vends->isEmpty()) {
            $this->warn('No smart freezers matched.');

            return self::SUCCESS;
        }

        foreach ($vends as $vend) {
            $items = $vend->productMapping?->productMappingItems()->count() ?? 0;
            if (! $this->option('apply')) {
                $this->line("{$vend->code}: {$items} planogram slot(s) would be pushed".($vend->product_mapping_id ? '' : ' (no mapping bound)'));

                continue;
            }
            $pushed = $sync->sync($vend);
            $this->info("{$vend->code}: {$pushed} slot(s) pushed");
        }

        if (! $this->option('apply')) {
            $this->warn('Dry run — pass --apply to write.');
        }

        return self::SUCCESS;
    }
}
