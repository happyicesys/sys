<?php

namespace App\Console\Commands;

use App\Services\GrabStoreService;
use Illuminate\Console\Command;

class GrabResumeDuePauses extends Command
{
    protected $signature = 'grab:resume-due-pauses';

    protected $description = 'Reopen Grab stores whose "Pause until" (Grab Stores page) has passed';

    public function handle(GrabStoreService $grabStores): int
    {
        $count = $grabStores->resumeDuePauses();
        $this->info("Resumed {$count} store(s).");

        return self::SUCCESS;
    }
}
