<?php

namespace App\Console\Commands;

use App\Models\VendTransaction;
use Illuminate\Console\Command;

class DeleteDuplicatedTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:duplicated-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete duplicated transactions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $vendTransactions = VendTransaction::groupBy('order_id')->havingRaw('COUNT(*) > 1')->get();

        foreach ($vendTransactions as $vendTransaction) {
            $duplicatedTransactions = VendTransaction::where('order_id', $vendTransaction->order_id)->get();

            $duplicatedTransactions->skip(1)->delete();
        }
    }
}
