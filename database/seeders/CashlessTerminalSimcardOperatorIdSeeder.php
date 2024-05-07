<?php

namespace Database\Seeders;

use App\Models\CashlessTerminal;
use App\Models\Simcard;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CashlessTerminalSimcardOperatorIdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cashlessTerminals = CashlessTerminal::all();
        $simcards = Simcard::all();

        foreach($cashlessTerminals as $cashlessTerminal) {
            $cashlessTerminal->update([
                'operator_id' => 1,
            ]);
        }

        foreach($simcards as $simcard) {
            $simcard->update([
                'operator_id' => 1,
            ]);
        }
    }
}
