<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\VendTransaction;
use Illuminate\Console\Command;

class SyncCustomerVendBindingVendTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:customer-vend-binding-vend-transactions {vendCodes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync customer vend binding in vend transactions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $vendCodesStr = $this->argument('vendCodes');
        if(strpos($vendCodesStr, ',') !== false) {
            $vendCodesArr = explode(',', $vendCodesStr);
        }else {
            $vendCodesArr = [$vendCodesStr];
        }

        $vendTransactions = VendTransaction::whereHas('vend', function($query) use ($vendCodesArr) {
            $query->whereIn('code', $vendCodesArr);
        })->get();

        foreach($vendTransactions as $vendTransaction) {
            $vendTransaction->customer_json = Customer::where('code', 'AR-'.$vendTransaction->vend->code)->first();
            $vendTransaction->save();
        }
    }
}
