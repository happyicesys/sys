<?php

namespace App\Console\Commands;

use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncTotalsJsonCustomer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:totals-json-customer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $customers = Customer::has('vend')->where('is_active', true)->get();

        foreach($customers as $customer) {
            SyncVendTransactionTotalsJson::dispatch($customer);
        }
    }
}
