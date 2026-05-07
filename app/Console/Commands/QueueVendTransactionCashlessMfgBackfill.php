<?php

namespace App\Console\Commands;

use App\Jobs\BackfillVendTransactionCashlessMfg;
use App\Models\VendTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Throttled, resumable backfill for vend_transactions.cashless_mfg.
 *
 * Mirrors the QueueVendTransactionQuantitiesBackfill pattern:
 *   - Walks the PK in fixed-size ID windows (no offset, no NULL-filter scan
 *     in the discovery phase).
 *   - Dispatches one BackfillVendTransactionCashlessMfg job per window;
 *     that job's UPDATE is what filters payment_method_id = 2 and
 *     cashless_mfg IS NULL, so non-credit-card / already-done rows are
 *     skipped at the SQL level.
 *   - Caches the last processed ID so subsequent invocations resume.
 *   - Cache lock prevents two cron/manual runs from overlapping.
 *
 * Typical usage:
 *   # Make sure a worker is consuming the low queue:
 *   php artisan queue:work --queue=low,default
 *
 *   # Dispatch a small batch, see how it behaves:
 *   php artisan vend:queue-backfill-cashless-mfg --max-jobs=5
 *
 *   # Dispatch the rest in larger waves (cron-friendly):
 *   php artisan vend:queue-backfill-cashless-mfg --max-jobs=50 --delay=30
 *
 *   # Start over from scratch:
 *   php artisan vend:queue-backfill-cashless-mfg --reset
 */
class QueueVendTransactionCashlessMfgBackfill extends Command
{
    protected $signature = 'vend:queue-backfill-cashless-mfg
        {--chunk=10000 : Number of transaction IDs per chunk}
        {--max-jobs=10 : Maximum number of jobs to dispatch per run}
        {--delay=0 : Delay in seconds between dispatched jobs}
        {--queue=low : Queue name to dispatch jobs on}
        {--start-id= : Optional lower bound ID to start from}
        {--reset : Reset the progress marker and start from the first record}';

    protected $description = 'Queue throttled jobs to backfill cashless_mfg on vend_transactions for credit-card payments.';

    private const CACHE_KEY_LAST_ID = 'vend_tx_cashless_mfg_backfill_last_id';
    private const CACHE_KEY_LOCK    = 'vend_tx_cashless_mfg_backfill_lock';

    public function handle(): int
    {
        $chunkSize    = max((int) $this->option('chunk'), 1000);
        $maxJobs      = max((int) $this->option('max-jobs'), 1);
        $delaySeconds = max((int) $this->option('delay'), 0);
        $queueName    = $this->option('queue') ?: 'low';

        if ($this->option('reset')) {
            Cache::forget(self::CACHE_KEY_LAST_ID);
            $this->info('Progress marker reset.');
        }

        // Prevent overlap if this is run by both cron and a human.
        $lock = Cache::lock(self::CACHE_KEY_LOCK, 10);

        if (! $lock->get()) {
            $this->warn('Another cashless_mfg backfill process is running. Try again later.');
            return self::FAILURE;
        }

        try {
            $minId = VendTransaction::min('id');
            $maxId = VendTransaction::max('id');

            if (! $minId || ! $maxId) {
                $this->info('No vend transactions found.');
                return self::SUCCESS;
            }

            $startingId = $this->option('start-id') !== null
                ? max((int) $this->option('start-id'), (int) $minId)
                : ($this->getLastProcessedId() ?? (int) $minId);

            if ($startingId > $maxId) {
                $this->info('Backfill already completed.');
                return self::SUCCESS;
            }

            $dispatched   = 0;
            $currentStart = $startingId;

            while ($dispatched < $maxJobs && $currentStart <= $maxId) {
                $currentEnd = min($currentStart + $chunkSize - 1, (int) $maxId);
                $delay      = $delaySeconds ? now()->addSeconds($delaySeconds * $dispatched) : now();

                BackfillVendTransactionCashlessMfg::dispatch($currentStart, $currentEnd)
                    ->delay($delay)
                    ->onQueue($queueName);

                $this->setLastProcessedId($currentEnd);

                $this->line(sprintf(
                    'Queued backfill job for IDs %d - %d on queue "%s" with delay %d seconds.',
                    $currentStart,
                    $currentEnd,
                    $queueName,
                    $delaySeconds * $dispatched
                ));

                $currentStart = $currentEnd + 1;
                $dispatched++;
            }

            if ($currentStart > $maxId) {
                $this->info('All chunks have been queued for processing.');
            } else {
                $this->info(sprintf(
                    'Progress saved at ID %d. Run the command again to queue the next batch.',
                    $this->getLastProcessedId()
                ));
            }
        } finally {
            optional($lock)->release();
        }

        return self::SUCCESS;
    }

    private function getLastProcessedId(): ?int
    {
        $value = Cache::get(self::CACHE_KEY_LAST_ID);
        return $value !== null ? (int) $value : null;
    }

    private function setLastProcessedId(int $id): void
    {
        Cache::forever(self::CACHE_KEY_LAST_ID, $id);
    }
}
