<?php

namespace App\Console\Commands;

use App\Models\VisitorPageView;
use App\Models\VisitorSession;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Retention for Admin > Visitor History. Deletes in chunks so a long-neglected
 * table can never lock up MySQL with one giant DELETE.
 */
class PruneVisitorHistory extends Command
{
    protected $signature = 'visitor-history:prune {--days= : Days of history to retain (defaults to config visitor_history.retention_days)}';

    protected $description = 'Delete visitor history (login sessions + page views) older than the retention window.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('visitor_history.retention_days', 90));
        if ($days <= 0) {
            $days = 90;
        }

        $cutoff = Carbon::now()->subDays($days);

        $views = 0;
        do {
            $deleted = VisitorPageView::where('viewed_at', '<', $cutoff)->limit(5000)->delete();
            $views += $deleted;
        } while ($deleted > 0);

        $sessions = 0;
        do {
            $deleted = VisitorSession::where('login_at', '<', $cutoff)->limit(5000)->delete();
            $sessions += $deleted;
        } while ($deleted > 0);

        $this->info("Pruned {$views} page view(s) and {$sessions} visitor session(s) older than {$days} days (cutoff: {$cutoff->toDateTimeString()}).");

        return Command::SUCCESS;
    }
}
