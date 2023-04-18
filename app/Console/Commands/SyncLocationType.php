<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;

class SyncLocationType extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-location-type';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Customer Location type from from admin happyice';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = 'https://admin.happyice.com.sg/api/person/migrate';

        ProcessCustomerLocationType::dispatch(null, $url);
    }
}
