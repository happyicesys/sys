<?php

namespace App\Console\Commands;

use App\Jobs\PollCityboxOpenapi;
use App\Services\Citybox\CityboxOpenapiSync;
use Illuminate\Console\Command;

/**
 * Scheduled every minute; a silent no-op unless the openapi integration is
 * enabled AND at least one Smart Chiller vend carries a citybox_equipment_id.
 * Both guards are O(1) so the empty tick costs nothing.
 */
class CityboxOpenapiPoll extends Command
{
    protected $signature = 'citybox:openapi-poll';

    protected $description = 'Poll CityBox-Openapi status + live stock for linked Smart Chiller vends';

    public function handle(): int
    {
        if (! config('citybox.openapi.enabled')) {
            return self::SUCCESS;
        }
        if (! CityboxOpenapiSync::hasLinkedVends()) {
            return self::SUCCESS;
        }

        // Live stock / state / signal per device on the stock-check minutes only; the fleet
        // status (one call) every minute.
        $every = max(1, (int) config('citybox.stock_check_every_minutes', 10));
        $withStock = now()->minute % $every === 0;
        PollCityboxOpenapi::dispatch($withStock);
        $this->info('Citybox openapi poll dispatched'.($withStock ? ' (with stock check).' : '.'));

        return self::SUCCESS;
    }
}
