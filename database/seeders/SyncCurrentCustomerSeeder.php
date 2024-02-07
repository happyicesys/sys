<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Jobs\SyncVendCustomerCms;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SyncCurrentCustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::all();

        foreach($customers as $customer) {
            if($customer->vendBindings()->exists()) {
                foreach($customer->vendBindings as $vendBinding) {
                    SyncVendCustomerCms::dispatch($vendBinding->vend_id, $customer->person_id)->onQueue('default');
                }
            }
        }
    }
}
