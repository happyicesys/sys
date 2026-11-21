<?php

namespace App\Console\Commands;

use App\Services\Grab\Menu\GrabMenuSyncTracker;
use Illuminate\Console\Command;

/**
 * The system's own check behind Grab Menu Sync (Brian, 2026-11-21: "should self check if Grab already has the
 * price rule"), every 10 minutes: moves on rows whose next step never came and sends again any store whose
 * prices on Grab (the menu it last pulled) differ from what its price rule gives now.
 */
class GrabCheckMenuSync extends Command
{
    protected $signature = 'grab:check-menu-sync {--operator=* : Only these operator ids}';

    protected $description = 'Check every Grab store has the menu its price rule gives; send again where not';

    public function handle(GrabMenuSyncTracker $tracker): int
    {
        $operators = array_map('intval', (array) $this->option('operator'));
        $result = $tracker->check($operators ?: null);

        $this->info("Moved on {$result['moved']} stuck row(s); {$result['matched']} store(s) match, {$result['differ']} differ, {$result['resent']} sent again.");

        return self::SUCCESS;
    }
}
