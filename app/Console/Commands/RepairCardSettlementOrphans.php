<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Services\CardSettlement\CardSettlementOrphanRepair;
use App\Services\CardSettlement\CardSettlementRefundReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Seed / repair: replace NETS orphan sales (code 99, "Created from report")
 * with the machine's own sale where one fits the line on EITHER anchor —
 * frame time or receive time — then re-run the refund reconciler on the
 * days touched. See CardSettlementOrphanRepair for the rule.
 *
 *   php artisan card-settlement:repair-orphans --from=2026-08-01            # dry run
 *   php artisan card-settlement:repair-orphans --from=2026-08-01 --vend=2760 --apply
 *   php artisan card-settlement:repair-orphans --from=2026-08-01 --apply
 */
class RepairCardSettlementOrphans extends Command
{
    protected $signature = 'card-settlement:repair-orphans
        {--from= : first sale day (Y-m-d), default 2026-08-01}
        {--to= : last sale day (Y-m-d), default today}
        {--vend= : machine code, repair this machine only}
        {--apply : write (default is a dry run)}';

    protected $description = 'Replace NETS orphan sales with the real machine sale that fits the line on frame OR receive time (dry-run by default)';

    public function handle(CardSettlementOrphanRepair $repair, CardSettlementRefundReconciler $reconciler): int
    {
        $apply = (bool) $this->option('apply');
        $from = Carbon::parse($this->option('from') ?: '2026-08-01')->startOfDay();
        $to = ($this->option('to') ? Carbon::parse($this->option('to')) : Carbon::today())->endOfDay();

        $vendId = null;
        if ($code = $this->option('vend')) {
            $vend = Vend::withoutGlobalScopes()->where('code', $code)->first();
            if (! $vend) {
                $this->error("No machine with code {$code}.");

                return self::FAILURE;
            }
            $vendId = $vend->id;
        }

        $plan = $repair->plan($from, $to, $vendId);
        if ($plan->isEmpty()) {
            $this->info('No orphan sale awaiting a TRADE in range.');

            return self::SUCCESS;
        }

        $codes = Vend::withoutGlobalScopes()->whereIn('id', $plan->pluck('orphan.vend_id')->unique())->pluck('code', 'id');
        $repairable = $plan->filter(fn ($e) => $e['sale'] !== null);
        $stuck = $plan->reject(fn ($e) => $e['sale'] !== null);

        $this->table(
            ['Machine', 'Line time', 'Cents', 'Orphan', 'Real sale', 'Sale frame time', 'Anchor', 'Δ s'],
            $repairable->map(fn ($e) => [
                $codes->get($e['orphan']->vend_id),
                (string) $e['orphan']->transaction_datetime,
                $e['orphan']->amount,
                $e['orphan']->id,
                $e['sale']->id,
                (string) $e['sale']->transaction_datetime,
                $e['anchor'],
                $e['delta'],
            ])->all()
        );
        $this->info(sprintf(
            '%d orphan(s) in range: %d repairable ($%s), %d left as genuine orphans.',
            $plan->count(), $repairable->count(), number_format($repairable->sum(fn ($e) => $e['orphan']->amount) / 100, 2), $stuck->count()
        ));
        foreach ($stuck->groupBy('reason') as $reason => $group) {
            $this->line(sprintf('  %d × %s', $group->count(), $reason));
        }

        if (! $apply) {
            $this->comment('Dry run — re-run with --apply to write.');

            return self::SUCCESS;
        }

        $days = collect();
        $done = 0;
        foreach ($repairable as $entry) {
            $touched = $repair->apply($entry);
            if ($touched) {
                $done++;
                $days = $days->merge($touched);
            }
        }
        $this->info("Repaired {$done} orphan(s).");

        foreach ($days->unique()->sort()->values() as $day) {
            $s = $reconciler->reconcileDay(Carbon::parse($day), true);
            $this->line(sprintf(
                '  reconciled %s: %d card sale(s), %d cleared (captured), %d tick(s) released, %d state(s) written',
                $day, $s['candidates'], $s['cleared_captured'], $s['tickets_released'], $s['states_written']
            ));
        }
        $this->comment('Rollups for the touched days rebuild at 02:00 (reconcile:sales-rollups --dirty), or run it now.');

        return self::SUCCESS;
    }
}
