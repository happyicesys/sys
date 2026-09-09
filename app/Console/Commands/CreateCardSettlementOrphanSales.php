<?php

namespace App\Console\Commands;

use App\Models\CardSettlementReport;
use App\Services\CardSettlement\CardSettlementOrphanSales;
use App\Services\CardSettlement\CardSettlementRefundReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Seed: create the Part 2 orphan sales for reports that were ALREADY synced
 * before Sync started creating them (CardSettlementOrphanSales). Same rule as
 * Sync, then the reconciler re-runs on the days each report finalises so the
 * new rows get their state (and REFUNDED when their line is reversed).
 *
 *   php artisan card-settlement:create-orphan-sales                 # report
 *   php artisan card-settlement:create-orphan-sales --from=2026-08-01 --apply
 *
 * Reports still in review are NOT touched: Sync is the human "this report is
 * settled" step and creation happens there.
 */
class CreateCardSettlementOrphanSales extends Command
{
    protected $signature = 'card-settlement:create-orphan-sales
        {--from= : first cutover day (Y-m-d)}
        {--to= : last cutover day (Y-m-d)}
        {--apply : write (default is a dry run)}';

    protected $description = 'Create vend_transactions rows (code 99) for unmatched NETS lines of already-synced reports (dry-run by default)';

    public function handle(CardSettlementOrphanSales $orphans, CardSettlementRefundReconciler $reconciler): int
    {
        $apply = (bool) $this->option('apply');
        $reports = CardSettlementReport::query()
            ->where('status', CardSettlementReport::STATUS_SYNCED)
            ->when($this->option('from'), fn ($q, $d) => $q->where('cutover_date', '>=', Carbon::parse($d)->toDateString()))
            ->when($this->option('to'), fn ($q, $d) => $q->where('cutover_date', '<=', Carbon::parse($d)->toDateString()))
            ->orderBy('cutover_date')
            ->get();

        if ($reports->isEmpty()) {
            $this->warn('No synced report in range.');

            return self::SUCCESS;
        }

        $rows = [];
        $total = 0;
        $days = collect();
        foreach ($reports as $report) {
            $n = $apply ? $orphans->createForReport($report) : $orphans->candidates($report)->count();
            $total += $n;
            $rows[] = [$report->id, $report->cutover_date, $report->original_filename, $n];
            if ($n && $apply) {
                $days = $days->merge(collect(CardSettlementRefundReconciler::daysCoveredBy($report))->map->toDateString());
            }
        }
        $this->table(['Report', 'Cutover', 'File', $apply ? 'Created' : 'Would create'], $rows);
        $this->info(sprintf('%s %d orphan sale(s) across %d report(s).', $apply ? 'Created' : 'Would create', $total, $reports->count()));

        if ($apply && $days->isNotEmpty()) {
            foreach ($days->unique()->sort()->values() as $day) {
                $s = $reconciler->reconcileDay(Carbon::parse($day), true);
                $this->line(sprintf('  reconciled %s: %d card sale(s), %d orphan(s) refunded, %d state(s) written', $day, $s['candidates'], $s['orphans_refunded'], $s['states_written']));
            }
        }
        if (! $apply) {
            $this->comment('Dry run — re-run with --apply to write.');
        }

        return self::SUCCESS;
    }
}
