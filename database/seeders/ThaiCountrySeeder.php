<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ThaiCountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Country::create([
            'name' => 'Thailand',
            'code' => 'TH',
            'currency_name' => 'THB',
            'currency_symbol' => '฿',
            'phone_code' => '66',
            'is_state' => true,
            'sequence' => 4,
        ]);
    }
}
