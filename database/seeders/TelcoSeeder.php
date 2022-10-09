<?php

namespace Database\Seeders;

use App\Models\Telco;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TelcoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Telco::create([
            'name' => 'Singtel (IMSI)'
        ]);

        Telco::create([
            'name' => 'Starhub (ICCID)'
        ]);

        Telco::create([
            'name' => 'M1'
        ]);

        Telco::create([
            'name' => 'Redone'
        ]);
    }
}
