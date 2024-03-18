<?php

namespace App\Console\Commands;

use App\Jobs\Vend\SyncVendTransactionTotalsJson as SyncTotalsJson;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncVendTransactionTotalsJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:totals-json';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync transaction total json';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $customers = Customer::has('vends')->whereNull('totals_json')->take(50);
        // dd($customers);

        foreach($customers as $customer) {
            SyncTotalsJson::dispatch($customer);
        }


    }
}
