<?php

namespace Database\Seeders;

use App\Models\CashlessProvider;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CashlessProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        CashlessProvider::create([
            'name' => 'Nayax'
        ]);

        CashlessProvider::create([
            'name' => 'Castle'
        ]);

        CashlessProvider::create([
            'name' => 'XVend'
        ]);

        CashlessProvider::create([
            'name' => 'Auresys'
        ]);

        CashlessProvider::create([
            'name' => 'Beeptech'
        ]);

    }
}
