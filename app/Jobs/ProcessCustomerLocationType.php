<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\LocationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class ProcessCustomerLocationType implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dataArr;
    protected $endPointUrl;

    public function __construct($dataArr, $endPointUrl)
    {
        $this->dataArr = $dataArr;
        $this->endPointUrl = $endPointUrl;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if($this->dataArr) {
            $customersCollection = collect([$this->dataArr]);
        }else {
            $response = Http::get($this->endPointUrl);
            $customersCollection = $response->collect();
        }

        if($customersCollection) {
            foreach($customersCollection as $customerCollection) {

                $bindedCustomer = Customer::has('vendBinding')->where('code', $customerCollection['cust_id'])->first();
                if($bindedCustomer) {
                    // $bindedCustomer
                }
            }
        }
    }
}
