<?php

namespace App\Console\Commands;

use App\Models\VendTransaction;
use App\Jobs\SyncDecenteriseVendTransactions;
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
        $vendTransactions = VendTransaction::whereNull('customer_id')
            ->orWhereNull('operator_id')
            ->orWhereNull('vend_channel_code')
            ->get();

        SyncDecenteriseVendTransactions::dispatch($vendTransactions);
        foreach ($vendTransactions as $vendTransaction) {
            $vend = $vendTransaction->vend;

            $vendTransaction->customer_id = $vend->latestVendBinding()->exists() && $vend->latestVendBinding->customer()->exists() ? $vend->latestVendBinding->customer->id : null;
            $vendTransaction->operator_id = $vend->currentOperator()->exists() ? $vend->currentOperator->first()->id : null;
            $vendTransaction->vend_channel_code = $vendTransaction->vendChannel->code;
            $vendTransaction->save();
        }
    }
}
