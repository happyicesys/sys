<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCustomerData;
use App\Models\Bank;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
use App\Models\PaymentTerm;
use App\Models\Profile;
use App\Models\Status;
use App\Models\Tax;
use App\Models\Vend;
use App\Models\Zone;
use App\Traits\SearchAddress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Propaganistas\LaravelPhone\PhoneNumber;

class SyncCustomerApi extends Command
{
    use SearchAddress;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:customers-api';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrieve customers json from admin happyice vend-code';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {
        // $url = 'https://admin.happyice.com.sg/api/person/migrate';
        $url = env('CMS_URL') . '/api/person/migrate';

        ProcessCustomerData::dispatch(null, $url);
    }
}
