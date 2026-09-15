<?php

namespace App\Console\Commands;

use App\Models\FreezerControlCommand;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes smart-freezer log uploads older than the retention window and clears their rows'
 * `log_path`; the rows themselves (who did what, verdict, excerpt) stay.
 */
class PruneFreezerLogs extends Command
{
    protected $signature = 'freezer-logs:prune {--days=30}';

    protected $description = 'Delete smart-freezer full-log uploads older than --days';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subDays((int) $this->option('days'));
        $n = 0;
        FreezerControlCommand::whereNotNull('log_path')->where('created_at', '<', $cutoff)
            ->orderBy('id')->chunkById(200, function ($rows) use (&$n) {
                foreach ($rows as $row) {
                    Storage::disk('local')->delete($row->log_path);
                    $row->update(['log_path' => null]);
                    $n++;
                }
            });
        $this->info("Pruned $n log upload(s) older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
