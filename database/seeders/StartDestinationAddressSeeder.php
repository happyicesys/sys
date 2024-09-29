<?php

namespace Database\Seeders;

use App\Models\Address;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StartDestinationAddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Address::create([
            'name' => 'Happy Ice Pte Ltd',
            'unit_num' => '01-198',
            'street_name' => 'BUKIT BATOK STREET 23',
            'postcode' => '659526',
            'country_id' => 1,
            'latitude' => '1.342409',
            'longitude' => '103.752863',
            'type' => 'start',
            'block_num' => '2021',
            'building' => 'BUKIT BATOK INDUSTRIAL ESTATE PARK A',
            'full_address' => '2021 BUKIT BATOK STREET 23 BUKIT BATOK INDUSTRIAL ESTATE PARK A SINGAPORE 659526',
        ]);

        Address::create([
            'name' => 'Happy Ice Pte Ltd',
            'unit_num' => '01-198',
            'street_name' => 'BUKIT BATOK STREET 23',
            'postcode' => '659526',
            'country_id' => 1,
            'latitude' => '1.342409',
            'longitude' => '103.752863',
            'type' => 'destination',
            'block_num' => '2021',
            'building' => 'BUKIT BATOK INDUSTRIAL ESTATE PARK A',
            'full_address' => '2021 BUKIT BATOK STREET 23 BUKIT BATOK INDUSTRIAL ESTATE PARK A SINGAPORE 659526',
        ]);
    }
}
