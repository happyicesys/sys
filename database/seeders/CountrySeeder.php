<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Country::create([
            'name' => 'Singapore',
            'currency_name' => 'SGD',
            'currency_symbol' => 'S$',
            'phone_code' => '65',
            'is_state' => false,
            'sequence' => 1,
        ]);

        Country::create([
            'name' => 'Malaysia',
            'currency_name' => 'MYR',
            'currency_symbol' => 'RM',
            'phone_code' => '60',
            'is_state' => true,
            'sequence' => 2,
        ]);

        Country::create([
            'name' => 'China',
            'currency_name' => 'RMB',
            'currency_symbol' => '¥',
            'phone_code' => '86',
            'is_state' => true,
            'sequence' => 3,
        ]);

    }
}
