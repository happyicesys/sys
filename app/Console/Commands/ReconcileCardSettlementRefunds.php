<?php

namespace App\Console\Commands;

use App\Models\CardSettlementReport;
use App\Services\CardSettlement\CardSettlementRefundReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Re-apply the NETS settlement report as the source of truth for the
 * auto-refund tick over days whose reports are ALREADY synced — Sync only
 * reconciles at the moment it runs, so reports synced before 2026-09-08
 * (when the reconciler was introduced) still carry the TRADE-time inference
 * ticks the report contradicts. Same rules as Sync
 * (CardSettlementRefundReconciler); dry-run unless --apply.
 *
 *   php artisan card-settlement:reconcile-refunds --from=2026-08-31 --to=2026-09-06
 *   php artisan card-settlement:reconcile-refunds --from=2026-08-31 --to=2026-09-06 --apply
 *
 * Without --from/--to it covers every day a synced report can finalise.
 */
class ReconcileCardSettlementRefunds extends Command
{
    protected $signature = 'card-settlement:reconcile-refunds
        {--from= : first calendar day (Y-m-d), default = earliest synced report day - 1}
        {--to= : last calendar day (Y-m-d), default = latest synced report day}
        {--apply : write the changes (default is a dry run)}';

    protected $description = 'Make the synced NETS settlement reports the source of truth for the auto-refund tick on card sales (dry-run by default)';

    public function handle(CardSettlementRefundReconciler $reconciler): int
    {
        $synced = CardSettlementReport::query()
            ->where('status', CardSettlementReport::STATUS_SYNCED)
            ->whereNotNull('cutover_date');

        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : ($synced->min('cutover_date') ? Carbon::parse($synced->min('cutover_date'))->subDay()->startOfDay() : null);
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->startOfDay()
            : ($synced->max('cutover_date') ? Carbon::parse($synced->max('cutover_date'))->startOfDay() : null);

        if (! $from || ! $to) {
            $this->warn('No synced settlement report — nothing to reconcile.');

            return self::SUCCESS;
        }
        if ($to->lt($from)) {
            $this->error('--to is before --from.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->info(($apply ? 'APPLYING' : 'DRY RUN').": {$from->toDateString()} → {$to->toDateString()}");

        $rows = [];
        $totals = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $s = $reconciler->reconcileDay($day, $apply);
            $rows[] = [
                $s['day'],
                $s['final'] ? 'yes' : 'no',
                $s['candidates'],
                $s['confirmed'],
                $s['relabelled'],
                $s['cleared_captured'],
                $s['cleared_not_captured'],
                $s['skipped_unbound'],
                $s['skipped_not_final'],
                $s['tickets_crossed'],
                $s['tickets_released'],
            ];
            foreach ($s as $k => $v) {
                if (is_int($v)) {
                    $totals[$k] = ($totals[$k] ?? 0) + $v;
                }
            }
        }

        $this->table(
            ['Day', 'Final?', 'Candidates', 'Set (reversal)', 'Relabelled', 'Cleared: captured', 'Cleared: not captured', 'Skipped: unbound', 'Skipped: not final', 'Tickets crossed', 'Tickets released'],
            $rows
        );

        $this->line(sprintf(
            'Totals — set %d, relabelled %d, cleared %d (captured %d, not captured %d), skipped %d (unbound %d, not final %d), tickets crossed %d, released %d.',
            $totals['confirmed'] ?? 0,
            $totals['relabelled'] ?? 0,
            ($totals['cleared_captured'] ?? 0) + ($totals['cleared_not_captured'] ?? 0),
            $totals['cleared_captured'] ?? 0,
            $totals['cleared_not_captured'] ?? 0,
            ($totals['skipped_unbound'] ?? 0) + ($totals['skipped_not_final'] ?? 0),
            $totals['skipped_unbound'] ?? 0,
            $totals['skipped_not_final'] ?? 0,
            $totals['tickets_crossed'] ?? 0,
            $totals['tickets_released'] ?? 0,
        ));

        if (! $apply) {
            $this->comment('Dry run — re-run with --apply to write.');
        }

        return self::SUCCESS;
    }
}
