<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SchedulerHeartbeat extends Command
{
    protected $signature = 'scheduler:heartbeat';
    protected $description = 'Logs a heartbeat every 5 minutes to confirm the scheduler is running';

    public function handle()
    {
        $message = '[SCHEDULER HEARTBEAT] Running at ' . now()->toDateTimeString() . ' (env: ' . config('app.env') . ')';

        Log::info($message);
        $this->info($message);

        return 0;
    }
}
