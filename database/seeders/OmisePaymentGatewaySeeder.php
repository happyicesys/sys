<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\PaymentGateways\Omise;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OmisePaymentGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $paymentGateway = PaymentGateway::create([
            'name' => 'omise',
            'classname' => get_class(new Omise()),
            'country_id' => 1,
            'key1_name' => 'Public Key',
            'key2_name' => 'Secret Key',
        ]);

        $paymentMethod = PaymentMethod::create([
            'code' => '201',
            'name' => 'Omise (Paynow)',
        ]);
    }
}
