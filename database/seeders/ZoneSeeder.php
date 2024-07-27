<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Zone;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::all();

        foreach ($customers as $customer) {
            $customer->update([
                'zone_id' => null,
            ]);
        }

        Zone::truncate();

        Zone::create([
            'name' => 'A1',
        ]);
        Zone::create([
            'name' => 'A2',
        ]);
        Zone::create([
            'name' => 'B1',
        ]);
        Zone::create([
            'name' => 'B2',
        ]);
    }
}
