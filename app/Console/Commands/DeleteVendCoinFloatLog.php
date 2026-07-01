<?php

namespace App\Console\Commands;

use App\Models\VendCoinFloatLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteVendCoinFloatLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:vend-coin-float-log {--days=14 : Number of days of coin-float history to retain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete coin-float change logs older than the retention window (default 14 days).';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $days = 14;
        }

        $cutoff = Carbon::now()->subDays($days);

        // Delete in bounded chunks so a large backlog never holds a long lock.
        $totalDeleted = 0;
        do {
            $deleted = VendCoinFloatLog::where('created_at', '<', $cutoff)
                ->limit(5000)
                ->delete();
            $totalDeleted += $deleted;
        } while ($deleted > 0);

        $this->info("Deleted {$totalDeleted} coin-float log record(s) older than {$days} days (cutoff: {$cutoff->toDateTimeString()}).");

        return Command::SUCCESS;
    }
}
