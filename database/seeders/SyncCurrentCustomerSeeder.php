<?php

namespace Database\Seeders;

use App\Models\Vend;
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
        $vends = Vend::has('customer')->get();

        // dd($vends->toArray());
        foreach($vends as $vend) {
            if($vend->customer->person_id) {
                SyncVendCustomerCms::dispatch($vend->customer->person_id, null)->onQueue('default');
            }
        }
    }
}
