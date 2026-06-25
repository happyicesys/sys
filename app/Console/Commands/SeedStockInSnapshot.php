<?php

namespace App\Console\Commands;

use App\Services\OpsMachineDailySnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Back-seed the frozen daily Stock-in value on the per-machine daily snapshot.
 *
 * For each date in range it recomputes the REAL per-machine stock-in value from
 * ops_jobs (which keep a real job date) and writes it onto that date's existing
 * snapshot rows. Machine STATE columns are left untouched — this only fills the
 * stock_in_cents column — so it will not re-stamp today's fleet state onto old
 * dates (unlike re-running ops:snapshot-daily).
 *
 * A date needs its state snapshot rows to exist first (ops:snapshot-daily), since
 * this command only UPDATEs existing rows; dates with no rows are reported and
 * skipped.
 */
class SeedStockInSnapshot extends Command
{
    protected $signature = 'ops:seed-stock-in
        {--date= : Seed a single YYYY-MM-DD}
        {--from= : Range start (YYYY-MM-DD); use with --to}
        {--to= : Range end (YYYY-MM-DD); defaults to yesterday}';

    protected $description = 'Back-seed the Stock-in Value column on ops_machine_daily_snapshots from ops_jobs (state untouched).';

    public function handle(): int
    {
        [$start, $end] = $this->resolveRange();
        if (! $start || ! $end) {
            $this->error('Specify --date, or --from/--to.');

            return self::FAILURE;
        }
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $this->info(sprintf('Seeding Stock-in Value %s..%s', $start->toDateString(), $end->toDateString()));

        $totalUpdated = 0;
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();

            $rowCount = DB::table(OpsMachineDailySnapshotBuilder::TABLE)
                ->where('snapshot_date', $date)
                ->count();
            if ($rowCount === 0) {
                $this->warn(sprintf(' - %s: no snapshot rows — run ops:snapshot-daily --date=%s first; skipped.', $date, $date));
                continue;
            }

            // Reset the day first so machines with no job that day end up NULL
            // (not stale) before re-stamping the ones that do have stock-in.
            DB::table(OpsMachineDailySnapshotBuilder::TABLE)
                ->where('snapshot_date', $date)
                ->update(['stock_in_cents' => null]);

            $map = OpsMachineDailySnapshotBuilder::stockInByVend($day);
            if (empty($map)) {
                $this->line(sprintf(' - %s: no completed jobs; cleared.', $date));
                continue;
            }

            $updated = $this->bulkUpdate($date, $map);
            $totalUpdated += $updated;
            $this->line(sprintf(' - %s: seeded %d machine(s).', $date, $updated));
        }

        $this->info(sprintf('Done. Updated %d snapshot row(s).', $totalUpdated));

        return self::SUCCESS;
    }

    /**
     * @return array{0:?Carbon,1:?Carbon}
     */
    private function resolveRange(): array
    {
        if ($this->option('date')) {
            $d = Carbon::parse($this->option('date'))->startOfDay();

            return [$d, $d->copy()];
        }

        if ($this->option('from') || $this->option('to')) {
            $start = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : Carbon::yesterday()->startOfDay();
            $end = $this->option('to') ? Carbon::parse($this->option('to'))->startOfDay() : Carbon::yesterday()->startOfDay();

            return [$start, $end];
        }

        return [null, null];
    }

    /**
     * One UPDATE per date: a CASE maps each machine to its stock-in cents. vend_id
     * and cents are integers straight from the DB, so inlining them is
     * injection-safe.
     *
     * @param  array<int,int>  $map  vend_id => cents
     */
    private function bulkUpdate(string $date, array $map): int
    {
        $cases = '';
        $ids = [];
        foreach ($map as $vendId => $cents) {
            $vendId = (int) $vendId;
            $cents = (int) $cents;
            $cases .= " WHEN {$vendId} THEN {$cents}";
            $ids[] = $vendId;
        }
        $idList = implode(',', $ids);

        return DB::update(
            'UPDATE ' . OpsMachineDailySnapshotBuilder::TABLE
            . " SET stock_in_cents = CASE vend_id{$cases} ELSE stock_in_cents END, updated_at = ?"
            . " WHERE snapshot_date = ? AND vend_id IN ({$idList})",
            [now(), $date]
        );
    }
}
