<?php

namespace App\Console\Commands;

use App\Jobs\Vend\SyncVendTransactionTotalsJson as SyncTotalsJson;
use App\Models\Vend;
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
        $vends = Vend::has('customer')->where('is_active', true)->get();
        // $vends = Vend::where('id', 166)->get();

        foreach($vends as $vend) {
            SyncTotalsJson::dispatch($vend);
        }


    }
}
