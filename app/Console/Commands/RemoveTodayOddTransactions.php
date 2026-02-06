<?php

namespace App\Console\Commands;

use App\Jobs\RemoveOddTransactions;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RemoveTodayOddTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remove:today-odd-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove transactions from today with amounts 0, 0.10, 0.20, 200.00 or with TEST operator';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $from = Carbon::today()->toDateString();
        $to = Carbon::today()->toDateString();

        RemoveOddTransactions::dispatch($from, $to);
    }
}
