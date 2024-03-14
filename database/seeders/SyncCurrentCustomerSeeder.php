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
        $vends = Vend::all();

        foreach($vends as $vend) {
            if($vend->customer()->exists()) {
                SyncVendCustomerCms::dispatch($vend->id, $vend->customer->person_id)->onQueue('default');
            }
        }
    }
}
