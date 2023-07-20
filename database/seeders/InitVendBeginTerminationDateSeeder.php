<?php

namespace Database\Seeders;

use App\Models\Vend;
use App\Models\VendBinding;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InitVendBeginTerminationDateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vends = Vend::all();
        foreach ($vends as $vend) {
            $vendBinding = VendBinding::where('vend_id', $vend->id)->latest('begin_date')->first();
            if($vendBinding) {
                $vend->begin_date = $vendBinding->begin_date;
                $vend->termination_date = $vendBinding->termination_date;
                $vend->save();
            }
        }
    }
}
