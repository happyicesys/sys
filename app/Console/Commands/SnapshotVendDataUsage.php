<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copy each machine's latest cumulative data-usage counters
 * (vends.internet_data_*, promoted from the VENDER packet by
 * SyncVendParameter) into vend_data_usage_snapshots, one row per machine per
 * day.
 *
 * The APK reports lifetime cumulative decimal KB, so this daily copy is what turns the
 * counters into per-window figures: last-30-days for a machine is today's row
 * minus the newest row at least 30 days old. Runs at 23:55 so a row reads as
 * "the cumulative total at the END of captured_on".
 *
 * Idempotent: upsert on (vend_id, captured_on), so a rerun in the same day
 * just refreshes the day's numbers. A machine that is offline keeps its last
 * cumulative value in vends, which snapshots unchanged — the diff for its
 * window is then 0, which is the truth.
 */
class SnapshotVendDataUsage extends Command
{
    protected $signature = 'vend:snapshot-data-usage';

    protected $description = 'Daily snapshot of per-machine cumulative data usage into vend_data_usage_snapshots';

    public function handle(): int
    {
        $now = Carbon::now();

        // Deliberately DB::table, not Vend::query(): scheduler context has no
        // viewer, and the operator global scopes must not decide which
        // machines get metered.
        $rows = DB::table('vends')
            ->whereNotNull('internet_data_kb')
            ->get(['id', 'code', 'internet_data_kb', 'internet_data_mobile_kb',
                'internet_data_app_kb', 'internet_data_days'])
            ->map(fn ($vend) => [
                'vend_id' => $vend->id,
                'vend_code' => is_numeric($vend->code) ? (int) $vend->code : null,
                'captured_on' => $now->toDateString(),
                'total_kb' => $vend->internet_data_kb,
                'mobile_kb' => $vend->internet_data_mobile_kb,
                'app_kb' => $vend->internet_data_app_kb,
                'ledger_days' => $vend->internet_data_days,
                'created_at' => $now,
            ])
            ->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('vend_data_usage_snapshots')->upsert(
                $chunk,
                ['vend_id', 'captured_on'],
                ['vend_code', 'total_kb', 'mobile_kb', 'app_kb', 'ledger_days', 'created_at'],
            );
        }

        $this->info(count($rows).' machine(s) snapshotted.');

        return self::SUCCESS;
    }
}
