<?php

namespace App\Console\Commands;

use App\Models\DeliveryPlatformApiError;
use Illuminate\Console\Command;

class GrabPruneApiErrors extends Command
{
    protected $signature = 'grab:prune-api-errors';

    protected $description = 'Delete Grab Errors older than 30 days';

    public function handle(): int
    {
        $deleted = 0;
        do {
            $batch = DeliveryPlatformApiError::query()
                ->where('created_at', '<', now()->subDays(DeliveryPlatformApiError::KEEP_DAYS))
                ->limit(5000)
                ->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Deleted {$deleted} Grab error(s) older than ".DeliveryPlatformApiError::KEEP_DAYS.' days.');

        return self::SUCCESS;
    }
}
