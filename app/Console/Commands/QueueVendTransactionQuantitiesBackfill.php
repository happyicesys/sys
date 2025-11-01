<?php

namespace App\Console\Commands;

use App\Jobs\Vend\BackfillVendTransactionQuantitiesChunk;
use App\Models\VendTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class QueueVendTransactionQuantitiesBackfill extends Command
{
    protected $signature = 'vend:queue-backfill-transaction-quantities
        {--chunk=20000 : Number of transactions per chunk}
        {--max-jobs=1 : Maximum number of jobs to dispatch per run}
        {--delay=60 : Delay in seconds between dispatched jobs}
        {--queue=low : Queue name to dispatch jobs on}
        {--start-id= : Optional lower bound ID to start from}
        {--reset : Reset the progress marker and start from the first record}';

    protected $description = 'Queue throttled jobs to backfill qty, success_qty, and dispensed_qty on vend_transactions.';

    private const CACHE_KEY_LAST_ID = 'vend_tx_quantities_backfill_last_id';

    public function handle(): int
    {
        $chunkSize = max((int) $this->option('chunk'), 1000);
        $maxJobs = max((int) $this->option('max-jobs'), 1);
        $delaySeconds = max((int) $this->option('delay'), 0);
        $queueName = $this->option('queue') ?? 'low';

        if ($this->option('reset')) {
            Cache::forget(self::CACHE_KEY_LAST_ID);
            $this->info('Progress marker reset.');
        }

        $lock = Cache::lock('vend_tx_quantities_backfill_lock', 10);

        if (! $lock->get()) {
            $this->warn('Another backfill queue process is running. Try again later.');
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
                ? max((int) $this->option('start-id'), $minId)
                : ($this->getLastProcessedId() ?? $minId);

            if ($startingId > $maxId) {
                $this->info('Backfill already completed.');
                return self::SUCCESS;
            }

            $dispatched = 0;
            $currentStart = $startingId;

            while ($dispatched < $maxJobs && $currentStart <= $maxId) {
                $currentEnd = min($currentStart + $chunkSize - 1, $maxId);
                $delay = $delaySeconds ? now()->addSeconds($delaySeconds * $dispatched) : now();

                BackfillVendTransactionQuantitiesChunk::dispatch($currentStart, $currentEnd)
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
        return Cache::get(self::CACHE_KEY_LAST_ID);
    }

    private function setLastProcessedId(int $id): void
    {
        Cache::forever(self::CACHE_KEY_LAST_ID, $id);
    }
}
