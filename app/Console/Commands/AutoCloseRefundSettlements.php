<?php

namespace App\Console\Commands;

use App\Models\RefundPayoutBatch;
use App\Models\RefundTicket;
use App\Services\Refund\RefundSettlementService;
use Illuminate\Console\Command;

/**
 * End-of-day safety net: close any Refund Settlement the admin left OPEN so the
 * day's PayNow batch is sealed and ready to export. Empty open settlements (all
 * tickets removed) are voided instead. Scheduled nightly in Console\Kernel.
 */
class AutoCloseRefundSettlements extends Command
{
    protected $signature = 'refund-settlements:auto-close {--dry-run : List what would happen without changing anything}';

    protected $description = 'Auto-close (or void if empty) any open refund settlements at end of day.';

    public function handle(RefundSettlementService $settlements): int
    {
        $open = RefundPayoutBatch::query()->settlements()
            ->where('status', RefundPayoutBatch::STATUS_OPEN)
            ->orderBy('id')
            ->get();

        if ($open->isEmpty()) {
            $this->info('No open settlements to close.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $closed = 0;
        $voided = 0;

        foreach ($open as $s) {
            $memberCount = RefundTicket::where('payout_batch_id', $s->id)
                ->whereIn('status', [RefundTicket::STATUS_SCHEDULED, RefundTicket::STATUS_COMPLETED])
                ->count();

            try {
                if ($memberCount === 0) {
                    $this->line(($dry ? '[dry] ' : '') . "Void empty {$s->reference}");
                    if (!$dry) {
                        $settlements->voidEmpty($s, null);
                    }
                    $voided++;
                } else {
                    $this->line(($dry ? '[dry] ' : '') . "Close {$s->reference} ({$memberCount} ticket(s))");
                    if (!$dry) {
                        $settlements->close($s, null, 'System (auto-close)');
                    }
                    $closed++;
                }
            } catch (\Throwable $e) {
                $this->error("Failed on {$s->reference}: {$e->getMessage()}");
            }
        }

        $this->info(($dry ? '[dry] ' : '') . "Done: {$closed} closed, {$voided} voided.");
        return self::SUCCESS;
    }
}
