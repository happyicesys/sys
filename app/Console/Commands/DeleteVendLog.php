<?php

namespace App\Console\Commands;

use App\Models\VendLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteVendLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:vend-log {--days=90 : Number of days of vend log history to retain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete vend logs (machine log history) older than the configured retention window (default 90 days).';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $days = 90;
        }

        $cutoff = Carbon::now()->subDays($days);

        // Use occurred_at when available, otherwise fall back to created_at so
        // legacy rows without occurred_at are still pruned.
        $deleted = VendLog::where(function ($query) use ($cutoff) {
            $query->where('occurred_at', '<', $cutoff)
                ->orWhere(function ($q) use ($cutoff) {
                    $q->whereNull('occurred_at')
                        ->where('created_at', '<', $cutoff);
                });
        })->delete();

        $this->info("Deleted {$deleted} vend log record(s) older than {$days} days (cutoff: {$cutoff->toDateTimeString()}).");

        return Command::SUCCESS;
    }
}
