<?php

namespace Database\Seeders;

use App\Models\Operator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OperatorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Operator::create([
            'code' => 'HIPL',
            'name' => 'Happy Ice Pte Ltd',
            'country_id' => 1,
            'is_active' => true,
            'timezone' => 'Asia/Singapore'
        ]);

        Operator::create([
            'code' => 'UTH',
            'name' => 'Unilever Thailand',
            'country_id' => 4,
            'is_active' => true,
            'timezone' => 'Asia/Bangkok'
        ]);
    }
}
