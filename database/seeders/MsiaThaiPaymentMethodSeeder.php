<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use App\Models\PaymentGateways\Omise;
use App\Models\PaymentMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MsiaThaiPaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $thaiOmise = PaymentGateway::create([
            'name' => 'omise',
            'country_id' => 4,
            'key1_name' => 'Public Key',
            'key2_name' => 'Secret Key',
        ]);

        PaymentMethod::create([
            'code' => 301,
            'name' => 'Omise (DuitNow)',
            'payment_gateway_id' => 3
        ]);

        PaymentMethod::create([
            'code' => 401,
            'name' => 'Omise (PromptPay)',
            'payment_gateway_id' => $thaiOmise->id,
        ]);
    }
}
