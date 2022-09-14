<?php

namespace Database\Seeders;

use App\Models\Uom;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Uom::create([
            'name' => 'pcs',
            'sequence' => 3,
            'color' => '#f0f0f0'
        ]);

        Uom::create([
            'name' => 'box',
            'sequence' => 2,
            'color' => '#dddddd'
        ]);

        Uom::create([
            'name' => 'ctn',
            'sequence' => 1,
            'color' => '#c9c9c9'
        ]);
    }
}
