<?php

namespace App\Console\Commands;

use App\Jobs\RemoveOddTransactions;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RemoveOddTransactionsByDateRange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remove:odd-transactions-range {from : The start date (YYYY-MM-DD)} {to : The end date (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove odd transactions within a specific date range';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $from = Carbon::parse($this->argument('from'))->toDateString();
        $to = Carbon::parse($this->argument('to'))->toDateString();

        $this->info("Dispatching RemoveOddTransactions job for range: $from to $to");

        RemoveOddTransactions::dispatch($from, $to);
    }
}
