<?php

namespace App\Console\Commands;

use App\Services\SmartFreezer\FreezerRecognitionService;
use Illuminate\Console\Command;

/**
 * Re-checks completed freezer AI results still waiting for their sale. A board that was offline
 * sends its TRADE long after the door session, so the result often lands first; this finds the sale
 * once it exists. Scheduled every 10 minutes; reads only the last week of results.
 */
class ZijiaEvaluatePending extends Command
{
    protected $signature = 'smart-freezer:zijia-evaluate-pending {--days=7}';

    protected $description = 'Judge freezer AI results whose sale has arrived since';

    public function handle(FreezerRecognitionService $recognitions): int
    {
        $judged = $recognitions->evaluateAwaitingSale(max(1, (int) $this->option('days')));
        if ($judged > 0) {
            $this->info("{$judged} recognition(s) now carry a verdict.");
        }

        return self::SUCCESS;
    }
}
