<?php

namespace App\Console\Commands;

use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\Vend;
use App\Services\Sales\OpsJobItemSalesWindow;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-run OpsJobItemSalesWindow ("Transaction S$ (Qty)" of an ops job stop) over
 * stops that are already complete.
 *
 * A stop's figures are written once, when the driver completes it. That is final
 * for a vending machine, whose TRADEs arrive within seconds. A Smart Chiller's
 * sales are CityBox orders pulled later — pay-later up to 14 days, and every
 * order before 2026-10-13 was created by that day's backfill — so its windows
 * keep filling in after the stop closed. Scheduled nightly for chillers after
 * the 01:20 31-day order pull; `--days` covers that pull's reach.
 *
 *   php artisan ops-jobs:recompute-sales-window                 # chillers, last 31 days
 *   php artisan ops-jobs:recompute-sales-window --all-time      # chillers, every stop
 *   php artisan ops-jobs:recompute-sales-window --vend=1362 --dry-run
 */
class RecomputeOpsJobSalesWindow extends Command
{
    protected $signature = 'ops-jobs:recompute-sales-window
                            {--days=31 : Stops completed in the last N days}
                            {--all-time : Every completed stop, whatever its age}
                            {--vend=* : Only these vend ids (any machine kind)}
                            {--dry-run : Report the changes, write nothing}';

    protected $description = "Recompute ops-job stops' sales since the previous stop (Smart Chillers by default)";

    private const FIELDS = ['acc_total_amount', 'acc_total_count', 'acc_total_cash_amount', 'acc_total_cashless_amount', 'acc_total_promo_amount'];

    public function handle(OpsJobItemSalesWindow $window): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $vendIds = array_map('intval', (array) $this->option('vend'));

        $query = OpsJobItem::query()
            ->whereNotNull('completed_at')
            ->where('status', '>=', OpsJob::STATUS_DELIVERED)
            ->where('status', '<>', OpsJob::STATUS_CANCELLED)
            ->with('vend')
            ->orderBy('id');

        if ($vendIds) {
            $query->whereIn('vend_id', $vendIds);
        } else {
            $query->whereIn('vend_id', Vend::query()->where('machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)->select('id'));
        }

        if (! $this->option('all-time')) {
            $query->where('completed_at', '>=', Carbon::now()->subDays(max(1, (int) $this->option('days')))->startOfDay());
        }

        $seen = 0;
        $changed = 0;
        foreach ($query->cursor() as $item) {
            $seen++;
            $before = $item->only(self::FIELDS);

            if ($dryRun) {
                // Same arithmetic, rolled back: the service writes through update().
                DB::beginTransaction();
                try {
                    $window->apply($item);
                    $after = $item->fresh()->only(self::FIELDS);
                } finally {
                    DB::rollBack();
                }
            } else {
                $window->apply($item);
                $after = $item->only(self::FIELDS);
            }

            if ($this->differs($before, $after)) {
                $changed++;
                $this->line(sprintf(
                    'item %d (vend %d, completed %s): %s → %s cents, %s → %s sales',
                    $item->id, $item->vend_id, $item->completed_at,
                    $before['acc_total_amount'] ?? 'null', $after['acc_total_amount'] ?? 'null',
                    $before['acc_total_count'] ?? 'null', $after['acc_total_count'] ?? 'null',
                ));
            }
        }

        $this->info(sprintf('%d stop(s) checked, %d %s.', $seen, $changed, $dryRun ? 'would change' : 'changed'));

        return self::SUCCESS;
    }

    private function differs(array $a, array $b): bool
    {
        foreach (self::FIELDS as $f) {
            if ((int) ($a[$f] ?? 0) !== (int) ($b[$f] ?? 0) || (($a[$f] ?? null) === null) !== (($b[$f] ?? null) === null)) {
                return true;
            }
        }

        return false;
    }
}
