<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupVendJobs extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vend:cleanup-jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete vend jobs older than 3 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = 3;
        $count = \App\Models\VendJob::where('created_at', '<', \Carbon\Carbon::now()->subDays($days))->delete();

        $this->info("Deleted {$count} old vend jobs.");
    }
}
