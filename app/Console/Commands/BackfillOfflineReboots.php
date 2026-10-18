<?php

namespace App\Console\Commands;

use App\Jobs\SyncP;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off (2026-10-18): estimate the `offline_reboot` daily stat for today and yesterday, the two
 * days the Ops Dashboard reads, so the restart alert splits by cause from the moment the split is
 * deployed. SyncP counts the stat from then on; before that only the board's running total
 * (`vends.offline_restart_count`, counted since `offline_restart_count_datetime`) exists.
 *
 * Estimate: today's reboots = min(today's restarts, the running total); yesterday's = min(its
 * restarts, what the total has left) — only while the total was already counting before today.
 * The running total already includes reboots SyncP counted after the deploy, so a day's row is
 * raised to the estimate, never added to (GREATEST). Dry run unless --apply.
 */
class BackfillOfflineReboots extends Command
{
    protected $signature = 'ops-attention:backfill-offline-reboots {--apply : write the rows}';

    protected $description = 'Estimate offline auto-reboots for today and yesterday from the boards\' running totals';

    public function handle(): int
    {
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();

        $restarts = DB::table('vend_daily_stats')
            ->where('metric', 'apk_restart')
            ->whereIn('date', [$today->toDateString(), $yesterday->toDateString()])
            ->get(['vend_id', 'vend_code', 'date', 'count'])
            ->groupBy('vend_id');

        $vends = DB::table('vends')
            ->whereIn('id', $restarts->keys())
            ->where('offline_restart_count', '>', 0)
            ->get(['id', 'code', 'offline_restart_count', 'offline_restart_count_datetime'])
            ->keyBy('id');

        $rows = [];
        foreach ($vends as $vend) {
            $left = (int) $vend->offline_restart_count;
            $since = $vend->offline_restart_count_datetime ? Carbon::parse($vend->offline_restart_count_datetime) : null;
            $byDate = $restarts->get($vend->id)->keyBy(fn ($row) => substr((string) $row->date, 0, 10));

            foreach ([$today, $yesterday] as $day) {
                $date = $day->toDateString();
                if ($day->lt($today) && ($since === null || $since->gte($today))) {
                    break; // the total only covers today
                }
                $count = min((int) ($byDate->get($date)->count ?? 0), $left);
                $left -= $count;
                if ($count > 0) {
                    $rows[] = ['vend_id' => $vend->id, 'vend_code' => (string) $vend->code, 'date' => $date, 'count' => $count];
                }
            }
        }

        $this->table(['vend', 'date', 'offline reboots'], array_map(fn ($r) => [$r['vend_code'], $r['date'], $r['count']], $rows));

        if (! $this->option('apply')) {
            $this->info(count($rows).' row(s) to write. Dry run: add --apply.');

            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            DB::statement(
                'INSERT INTO vend_daily_stats (vend_id, vend_code, date, metric, count, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE count = GREATEST(count, VALUES(count)), updated_at = NOW()',
                [$row['vend_id'], $row['vend_code'], $row['date'], SyncP::METRIC_OFFLINE_REBOOT, $row['count']]
            );
        }
        $this->info(count($rows).' row(s) written.');

        return self::SUCCESS;
    }
}
