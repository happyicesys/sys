<?php

namespace Database\Seeders;

use App\Models\VendType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VendTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        VendType::create([
            'name' => 'Combi',
            'desc' => 'Combi',
        ]);
        VendType::create([
            'name' => 'DVM',
            'desc' => 'Direct Vending Machine',
        ]);
        VendType::create([
            'name' => 'FVM',
            'desc' => 'Fun Vending Machine',
        ]);
        VendType::create([
            'name' => 'Model-E',
            'desc' => 'Model-E',
        ]);
        VendType::create([
            'name' => 'Model-F',
            'desc' => 'Model-F',
        ]);
    }
}
