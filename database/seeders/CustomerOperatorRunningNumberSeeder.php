<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Services\RunningNumberService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerOperatorRunningNumberSeeder extends Seeder
{
    protected $runningNumberService;

    public function __construct()
    {
        $this->runningNumberService = new RunningNumberService();
    }
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Customer::whereNull('operator_id')->update(['operator_id' => 1]);

        $customers = Customer::oldest()->get();
        foreach ($customers as $customer) {
            $customer->update(['code' => $this->runningNumberService->getCustomerRunningCode($customer->operator_id)]);
        }
    }
}
