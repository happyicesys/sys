<?php

namespace App\Console\Commands;

use App\Models\CardSettlementReport;
use App\Services\CardSettlement\CardSettlementUnsyncService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Finishes an Undo Sync / Delete whose unwind stopped part-way (audit CARD-06).
 *
 *   php artisan card-settlement:finish-unwind              every report Undo Sync left pending
 *   php artisan card-settlement:finish-unwind --report=12  just that one
 *   php artisan card-settlement:finish-unwind --days=2026-10-05,2026-10-06 [--top-up-sales=1,2]
 *                                                           a deleted report's days (the
 *                                                           command its failed Delete logged)
 *
 * The unwind is idempotent: a day already unwound changes nothing.
 */
class FinishCardSettlementUnwind extends Command
{
    protected $signature = 'card-settlement:finish-unwind
        {--report= : one report id (default: every report with an unwind pending)}
        {--days= : comma-separated Y-m-d days of a DELETED report}
        {--top-up-sales= : comma-separated sale ids whose top-up lines went with the deleted report}';

    protected $description = 'Finish the take-back of an Undo Sync or Delete that stopped part-way';

    public function handle(CardSettlementUnsyncService $unsync): int
    {
        if ($this->option('days')) {
            $days = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('days')))));
            $sales = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('top-up-sales')))));
            $this->report('days '.implode(', ', $days), $unsync->unwindDays($days, $sales));

            return self::SUCCESS;
        }

        $reports = CardSettlementReport::query()
            ->whereNotNull('unwind_pending_json')
            ->when($this->option('report'), fn ($q, $id) => $q->whereKey((int) $id))
            ->orderBy('id')
            ->get();
        if ($reports->isEmpty()) {
            $this->info('Nothing pending.');

            return self::SUCCESS;
        }

        $failed = false;
        foreach ($reports as $report) {
            try {
                $this->report("report #{$report->id} {$report->original_filename}", $unsync->finishPendingUnwind($report));
            } catch (Throwable $e) {
                report($e);
                $this->error("report #{$report->id}: {$e->getMessage()}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function report(string $what, array $r): void
    {
        $this->info(sprintf('%s: %d day(s) unwound, %d tick(s) cleared, %d ticket(s) released, %d link(s) removed.',
            $what, count($r['days'] ?? []), $r['ticks_cleared'] ?? 0, $r['tickets_released'] ?? 0, $r['links_removed'] ?? 0));
    }
}
