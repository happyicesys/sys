<?php

namespace App\Console\Commands;

use App\Jobs\PollCityboxEquipment;
use App\Services\Citybox\CityboxEquipmentSync;
use Illuminate\Console\Command;

/**
 * Scheduled every minute, but a no-op until Citybox is actually in use:
 * it exits before queueing anything unless the integration is enabled AND at
 * least one vend carries a citybox_equipment_id. Both guards are O(1) — a
 * config read and one indexed EXISTS — so the empty tick costs nothing.
 */
class CityboxPoll extends Command
{
    protected $signature = 'citybox:poll';

    protected $description = 'Poll Citybox equipment status + stock for linked vends (no-op when none are linked)';

    public function handle(): int
    {
        if (! config('citybox.enabled')) {
            return self::SUCCESS; // integration off — silent no-op
        }

        if (! CityboxEquipmentSync::hasLinkedVends()) {
            return self::SUCCESS; // no chiller created/bound yet — silent no-op
        }

        PollCityboxEquipment::dispatch();
        $this->info('Citybox poll dispatched.');

        return self::SUCCESS;
    }
}
