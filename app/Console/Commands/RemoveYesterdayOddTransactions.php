<?php

namespace App\Console\Commands;

use App\Jobs\RemoveOddTransactions;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RemoveYesterdayOddTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remove:yesterday-odd-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove yesterday transactions where has amount 0, 0.10, 200';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $from = Carbon::yesterday()->toDateString();
        $to = Carbon::yesterday()->toDateString();

        RemoveOddTransactions::dispatch($from, $to);
    }
}
