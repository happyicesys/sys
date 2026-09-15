<?php

namespace App\Console\Commands;

use App\Models\FreezerControlCommand;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Smart-freezer log retention: 72 hours (Brian, 2026-09-15), matching the archive on the machine.
 *
 * Deletes uploaded log files and stored excerpts older than --hours; the rows themselves (who did
 * what, when, the verdict and the host's message) stay as the audit trail.
 */
class PruneFreezerLogs extends Command
{
    protected $signature = 'freezer-logs:prune {--hours=72}';

    protected $description = 'Delete smart-freezer log uploads and excerpts older than --hours';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subHours((int) $this->option('hours'));
        $files = 0;
        $excerpts = 0;
        FreezerControlCommand::where('created_at', '<', $cutoff)
            ->where(fn ($q) => $q->whereNotNull('log_path')->orWhereNotNull('response_log'))
            ->orderBy('id')->chunkById(200, function ($rows) use (&$files, &$excerpts) {
                foreach ($rows as $row) {
                    if ($row->log_path) {
                        Storage::delete($row->log_path);
                        $files++;
                    }
                    if ($row->response_log !== null) {
                        $excerpts++;
                    }
                    $row->update(['log_path' => null, 'response_log' => null]);
                }
            });
        $this->info("Pruned $files log file(s) and $excerpts excerpt(s) older than {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
