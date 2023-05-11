<?php

namespace App\Console\Commands;

use App\Models\VendTransaction;
use App\Jobs\SyncDecenteriseVendTransactions;
use DB;
use Illuminate\Console\Command;

class DecenteriseVendTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decenterise:vend-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sync customer id operator id and vend channel code to vend transactions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $vendTransactions = DB::table('vend_transactions')
        ->leftJoin('vends', 'vends.id', '=', 'vend_transactions.vend_id')
        ->leftJoin('vend_channels', 'vend_channels.id', '=', 'vend_transactions.vend_channel_id')
        ->leftJoin('vend_bindings', function($query) {
            $query->on('vend_bindings.vend_id', '=', 'vends.id')
                    ->where('vend_bindings.is_active', true)
                    ->latest('begin_date')
                    ->limit(1);
        })
        ->leftJoin('customers', 'customers.id', '=', 'vend_bindings.customer_id')
        ->leftJoin('operator_vend', function($query) {
            $query->on('operator_vend.vend_id', '=', 'vends.id')
                    ->latest('operator_vend.begin_date')
                    ->limit(1);
        })
        ->whereNull('vend_transactions.vend_channel_code')
        ->select('vend_transactions.id', 'customers.id as customer_id', 'operator_vend.operator_id', 'vend_channels.code as vend_channel_code')
        ->get();

        foreach ($vendTransactions as $vendTransaction) {
            SyncDecenteriseVendTransactions::dispatch($vendTransaction);
        }
    }
}
