<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\VendPrefix;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VendPrefixSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::whereNotNull('person_id')->get();

        foreach($customers as $customer) {
            VendPrefix::updateOrCreate([
                'name' => $customer->virtual_customer_prefix,
            ], [
                'operator_id' => $customer->operator_id,
            ]);
        }
    }
}
