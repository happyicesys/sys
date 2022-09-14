<?php

namespace Database\Seeders;

use App\Models\PaymentTerm;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentTermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        PaymentTerm::create([
            'name' => 'C.O.D',
        ]);

        PaymentTerm::create([
            'name' => 'Prepaid',
        ]);

        PaymentTerm::create([
            'name' => 'In a Given # of Days',
        ]);

        PaymentTerm::create([
            'name' => 'On a Day of the Month',
        ]);

        PaymentTerm::create([
            'name' => '# of Days after EOM',
        ]);

        PaymentTerm::create([
            'name' => 'Day of Month after EOM',
        ]);

        PaymentTerm::create([
            'name' => '3 months',
        ]);

        PaymentTerm::create([
            'name' => '15 Days after EOM',
        ]);

        PaymentTerm::create([
            'name' => 'Pay on next delivery',
        ]);

        PaymentTerm::create([
            'name' => '15th & 30th of month',
        ]);

        PaymentTerm::create([
            'name' => '7 Days after EOM',
        ]);

        PaymentTerm::create([
            'name' => '30 Days',
        ]);

        PaymentTerm::create([
            'name' => '75 Days',
        ]);

        PaymentTerm::create([
            'name' => '60 Days',
        ]);
    }
}
