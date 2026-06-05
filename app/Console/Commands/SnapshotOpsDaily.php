<?php

namespace App\Console\Commands;

use App\Services\OpsMachineDailySnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SnapshotOpsDaily extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ops:snapshot-daily
        {--date= : Snapshot a specific YYYY-MM-DD instead of yesterday}
        {--from= : Start of a date range to backfill (YYYY-MM-DD); use with --to}
        {--to= : End of a date range to backfill (YYYY-MM-DD); defaults to yesterday}
        {--force : Skip the confirmation prompt for a range backfill}';

    /**
     * @var string
     */
    protected $description = 'Freeze the per-machine fleet state for the Ops Performance page (one row per machine).';

    public function handle(): int
    {
        // Range backfill mode. NOTE: the snapshot captures the CURRENT fleet state —
        // `vends` keeps no history — so each backfilled date records today's state,
        // not a true historical one. Kept behind an explicit --from/--to + confirm.
        if ($this->option('from') || $this->option('to')) {
            return $this->backfillRange();
        }

        // Default target is yesterday — the day that just ended. The capture
        // reflects the CURRENT fleet state, which is the correct end-of-day
        // value when run nightly.
        $day = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::yesterday();

        $count = OpsMachineDailySnapshotBuilder::persistDay($day);

        $this->info(sprintf('Ops machine snapshot stored for %s (%d machines).', $day->toDateString(), $count));

        return self::SUCCESS;
    }

    /**
     * Backfill the snapshot for every day in [--from, --to]. persistDay is
     * delete-then-insert per day, so re-running is idempotent.
     */
    private function backfillRange(): int
    {
        $start = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::yesterday();
        $end = $this->option('to')
            ? Carbon::parse($this->option('to'))->startOfDay()
            : Carbon::yesterday();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        // The fleet state only exists up to now — never stamp a future date.
        $today = Carbon::today();
        if ($end->gt($today)) {
            $end = $today->copy();
        }

        $days = $start->diffInDays($end) + 1;

        $this->warn(sprintf(
            'This writes the CURRENT fleet state onto each of %d date(s) (%s..%s). '
            . 'vends keeps no history, so these are NOT true historical snapshots — '
            . 'every backfilled day reflects the fleet as it looks today.',
            $days,
            $start->toDateString(),
            $end->toDateString()
        ));

        if (! $this->option('force') && ! $this->confirm('Proceed with the range backfill?')) {
            $this->info('Aborted — nothing written.');

            return self::SUCCESS;
        }

        $total = 0;
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $count = OpsMachineDailySnapshotBuilder::persistDay($day->copy());
            $total += $count;
            $this->line(sprintf(' - %s: %d machines', $day->toDateString(), $count));
        }

        $this->info(sprintf('Backfilled %d date(s), %d snapshot rows total.', $days, $total));

        return self::SUCCESS;
    }
}
