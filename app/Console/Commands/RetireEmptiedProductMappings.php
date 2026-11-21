<?php

namespace App\Console\Commands;

use App\Services\ProductMapping\MappingRetirement;
use Illuminate\Console\Command;

/**
 * Deactivates a mapping 3 days after its last machine moved off (rules in MappingRetirement). Hourly; without
 * --apply it only lists what it would deactivate.
 */
class RetireEmptiedProductMappings extends Command
{
    protected $signature = 'product-mappings:retire-emptied {--apply : Deactivate (default: list only)}';

    protected $description = 'Deactivate mappings whose machines all moved to the upcoming mapping 3 days ago';

    public function handle(MappingRetirement $retirement): int
    {
        $apply = (bool) $this->option('apply');
        $done = $retirement->run($apply);

        foreach ($done as $m) {
            $this->line(sprintf('%s #%d %s (last machine left %s)', $apply ? 'Deactivated' : 'Would deactivate', $m->id, $m->name, $m->left_at->toDateTimeString()));
        }
        $this->info(sprintf('%d mapping(s) %s.', $done->count(), $apply ? 'deactivated' : 'due'));

        return self::SUCCESS;
    }
}
