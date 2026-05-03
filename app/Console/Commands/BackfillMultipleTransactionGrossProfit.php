<?php

namespace App\Console\Commands;

use App\Jobs\StoreVendsRecord;
use App\Jobs\StoreVendProductRecords;
use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Models\Customer;
use App\Models\Vend;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;

class BackfillMultipleTransactionGrossProfit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backfill:multiple-txn-gp
                            {--from= : Start date (YYYY-MM-DD, default 35 days ago)}
                            {--to= : End date (YYYY-MM-DD, default yesterday)}
                            {--with-totals : Also dispatch SyncVendTransactionTotalsJson after record jobs (only safe once the record jobs have finished)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill gross_profit in vend_records and vend_product_records for is_multiple transactions that were inflated by a ×100 bug on unit_cost.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fromStr = $this->option('from') ?? Carbon::today()->subDays(35)->toDateString();
        $toStr   = $this->option('to')   ?? Carbon::yesterday()->toDateString();
        $queue   = 'low'; // always use low-priority queue to avoid jamming the main queue
        $withTotals = $this->option('with-totals');

        $startDate = Carbon::parse($fromStr);
        $endDate   = Carbon::parse($toStr);

        if ($startDate->gt($endDate)) {
            $this->error('--from date cannot be after --to date.');
            return 1;
        }

        $days = CarbonPeriod::create($startDate, $endDate);
        $dayCount = count($days);

        $this->info("Backfilling GP for is_multiple transactions from {$startDate->toDateString()} to {$endDate->toDateString()} ({$dayCount} days).");
        $this->comment("Queue: {$queue} (low priority — will not affect main queue)");
        $this->newLine();

        // ── Step 1: Re-run vend_records and vend_product_records per day ──────
        $this->line('<fg=cyan>Step 1/2 — Dispatching StoreVendsRecord + StoreVendProductRecords...</>');
        $bar = $this->output->createProgressBar($dayCount);
        $bar->start();

        foreach ($days as $date) {
            $dateStr = $date->toDateString();

            StoreVendsRecord::dispatch($dateStr, $dateStr, true)->onQueue($queue);
            StoreVendProductRecords::dispatch($dateStr, $dateStr)->onQueue($queue);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✓ {$dayCount} days of record jobs dispatched.");
        $this->newLine();

        // ── Step 2: Optionally re-sync 30-day totals on customers & vends ─────
        if ($withTotals) {
            $this->line('<fg=cyan>Step 2/2 — Dispatching SyncVendTransactionTotalsJson for all active customers & vends...</>');
            $this->warn('⚠  Make sure the record jobs from Step 1 have fully processed before running this step, otherwise totals will be recalculated from stale records.');
            $this->newLine();

            if (!$this->confirm('Have the Step 1 queue jobs finished processing?', false)) {
                $this->comment('Skipped totals sync. Re-run with --with-totals once the queue is clear.');
                return 0;
            }

            $customers = Customer::has('vend')->where('is_active', true)->get();
            $vends = Vend::has('customer')->where('is_active', true)->get();

            $total = $customers->count() + $vends->count();
            $bar2 = $this->output->createProgressBar($total);
            $bar2->start();

            foreach ($customers as $customer) {
                SyncVendTransactionTotalsJson::dispatch($customer)->onQueue($queue);
                $bar2->advance();
            }

            foreach ($vends as $vend) {
                SyncVendTransactionTotalsJson::dispatch($vend)->onQueue($queue);
                $bar2->advance();
            }

            $bar2->finish();
            $this->newLine();
            $this->info("✓ Totals sync dispatched for {$customers->count()} customers and {$vends->count()} vends.");
        } else {
            $this->line('<fg=yellow>Step 2/2 — Totals sync skipped.</>');
            $this->comment('Once the Step 1 queue jobs have finished, run:');
            $this->comment('  php artisan backfill:multiple-txn-gp --with-totals');
            $this->comment('Or separately:');
            $this->comment('  php artisan sync:totals-json');
            $this->comment('  php artisan sync:totals-json-customer');
        }

        $this->newLine();
        $this->info('Done.');

        return 0;
    }
}
