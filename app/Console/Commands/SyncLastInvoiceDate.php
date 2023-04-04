<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Vend;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncLastInvoiceDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:last-invoice-date';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync last invoice date for each binded customers';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public $endPointUrl = 'https://admin.happyice.com.sg/api/people/last-invoice-date';

    public function handle()
    {
        $response = Http::get($this->endPointUrl);
        $people = $response->collect();

        if($people) {
            foreach($people as $person) {
                $customer = Customer::where('code', $person->cust_id)->has('vendBinding')->first();
            }
        }
    }
}
