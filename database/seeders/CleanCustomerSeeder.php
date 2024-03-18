<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Vend;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CleanCustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vends = Vend::query()
                ->doesntHave('vendBindings')
                ->get();

        if($vends) {
            foreach($vends as $vend) {
                if($vend->name) {
                    if($vend->operators()->exists()) {
                        $operatorID = $vend->operators()->max('operator_id');
                    } else {
                        $operatorID = 1;
                    }
                    $customer = Customer::create([
                        'name' => $vend->name,
                        'code' => $vend->code,
                        'status_id' => $vend->is_active ? Customer::STATUS_ACTIVE : Customer::STATUS_INACTIVE,
                        'operator_id' => $operatorID,
                        'profile_id' => 1,
                    ]);

                    $customer->latestVendBinding()->create([
                        'vend_id' => $vend->id,
                        'begin_date' => $vend->created_at,
                    ]);
                }
            }
        }
    }
}
