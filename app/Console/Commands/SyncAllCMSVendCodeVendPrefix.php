<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Jobs\SyncVendCodeVendPrefixCMS;
use Illuminate\Console\Command;

class SyncAllCMSVendCodeVendPrefix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:all-cms-vend-code-vend-prefix';

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
        $vends = Vend::query()
            ->whereHas('customer', function($query) {
                $query->whereNotNull('person_id');
            })
            ->where('is_active', true)
            ->orderBy('code', 'asc')
            ->get();

        foreach ($vends as $vend) {
            SyncVendCodeVendPrefixCMS::dispatch($vend);
        }
    }
}
