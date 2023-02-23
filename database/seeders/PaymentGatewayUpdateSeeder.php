<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentGatewayUpdateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        PaymentGateway::where('name', 'midtrans')
            ->update([
                'key1_name' => 'Server Key',
                'key2_name' => 'Client Key',
                'key3_name' => 'Merchant ID',
                'country_id' => 5,
            ]);
    }
}
