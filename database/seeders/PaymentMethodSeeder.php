<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        PaymentMethod::create([
            'code' => 0,
            'name' => 'Cash',
        ]);

        PaymentMethod::create([
            'code' => 1,
            'name' => 'Credit Card',
        ]);

        PaymentMethod::create([
            'code' => 2,
            'name' => 'Debit Card',
        ]);

        PaymentMethod::create([
            'code' => 3,
            'name' => 'Union Pay',
        ]);

        PaymentMethod::create([
            'code' => 4,
            'name' => 'Alipay',
        ]);

        PaymentMethod::create([
            'code' => 5,
            'name' => 'Sonic Pay',
        ]);

        PaymentMethod::create([
            'code' => 6,
            'name' => 'Wechat Pay',
        ]);

        PaymentMethod::create([
            'code' => 7,
            'name' => 'Passcode',
        ]);

        PaymentMethod::create([
            'code' => 10,
            'name' => 'Free Vend',
        ]);
    }
}
