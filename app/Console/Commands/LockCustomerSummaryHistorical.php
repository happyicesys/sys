<?php

namespace App\Console\Commands;

use App\Models\CustomerPeriodSummary;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-lock every customer_period_summaries row whose year_month is on/before
 * --through=YYYY-MM. Used after a force-single-row backfill to freeze every
 * historical month, leaving only the current invoicing month(s) live.
 *
 * Lock semantics mirror the UI's lock action (see
 * CustomerController::lockCustomerPeriodSummary): the row's CURRENT stored
 * figures become the frozen snapshot, locked_at is stamped. Because a fresh
 * force-single-row backfill JUST wrote those stored columns from each
 * customer's current contract values, no re-snapshot is needed here — a simple
 * UPDATE setting locked_at = now() is correct.
 *
 * Examples:
 *   # Lock 2023-01 through April 2026 (keeps May + June 2026 unlocked)
 *   php artisan customer-summary:lock-historical --through=2026-04
 *
 *   # Same, but stamp an admin user_id as locked_by (default NULL)
 *   php artisan customer-summary:lock-historical --through=2026-04 --by=1
 *
 *   # Re-lock even rows already locked (force-overwrite locked_at to now)
 *   php artisan customer-summary:lock-historical --through=2026-04 --relock
 */
class LockCustomerSummaryHistorical extends Command
{
    protected $signature = 'customer-summary:lock-historical
        {--through= : Lock all rows where year_month <= this YYYY-MM (required)}
        {--by= : Optional user_id to stamp as locked_by (default NULL)}
        {--relock : Also re-stamp locked_at on rows that are ALREADY locked (overwrites their locked_at to NOW)}
        {--dry-run : Show how many rows WOULD be locked without writing}';

    protected $description = 'Bulk-lock customer_period_summaries rows up to and including a given month';

    public function handle(): int
    {
        $throughOpt = $this->option('through');
        if (!$throughOpt) {
            $this->error('--through=YYYY-MM is required.');
            return self::FAILURE;
        }

        try {
            $through = Carbon::parse($throughOpt)->startOfMonth();
        } catch (\Throwable $e) {
            $this->error('Invalid --through value: ' . $throughOpt);
            return self::FAILURE;
        }

        $byOpt = $this->option('by');
        $lockedBy = ($byOpt === null || $byOpt === '') ? null : (int) $byOpt;
        $relock = (bool) $this->option('relock');
        $dryRun = (bool) $this->option('dry-run');

        // Defensive: never lock the in-progress month. is_current_month is
        // set true when the row's month equals "today's month" at the time
        // of aggregation, so filtering it out belt-and-braces protects
        // against an operator passing a too-recent --through value.
        $base = CustomerPeriodSummary::query()
            ->where('year_month', '<=', $through->toDateString())
            ->where('is_current_month', false);

        if (!$relock) {
            $base->whereNull('locked_at');
        }

        $count = (clone $base)->count();

        $this->info(sprintf(
            'Lock target: year_month <= %s, is_current_month = false, locked_at %s. %d row(s) match.',
            $through->format('Y-m'),
            $relock ? 'ANY (relock mode)' : 'IS NULL (skip already-locked)',
            $count
        ));

        if ($count === 0) {
            $this->comment('Nothing to do.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->comment('--dry-run: no rows written.');
            return self::SUCCESS;
        }

        if (!$this->confirm(sprintf('Lock %d row(s) now?', $count), false)) {
            $this->comment('Aborted.');
            return self::SUCCESS;
        }

        $now = Carbon::now();
        $affected = DB::transaction(function () use ($base, $now, $lockedBy) {
            return (clone $base)->update([
                'locked_at' => $now,
                'locked_by' => $lockedBy,
                'updated_at' => $now,
            ]);
        });

        $this->info(sprintf('Locked %d row(s) at %s.', $affected, $now->toDateTimeString()));
        return self::SUCCESS;
    }
}
