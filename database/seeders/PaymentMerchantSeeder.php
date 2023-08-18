<?php

namespace Database\Seeders;

use App\Models\PaymentMerchant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentMerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PaymentMerchant::create([
            'name' => 'DuitNow',
            'image_url' =>  'https://happyice-space.sgp1.digitaloceanspaces.com/payment-merchant/duitnow.png',
        ]);

        PaymentMerchant::create([
            'name' => 'GrabPay',
            'image_url' =>  'https://happyice-space.sgp1.digitaloceanspaces.com/payment-merchant/grabpay.png',
        ]);

        PaymentMerchant::create([
            'name' => 'PayNow',
            'image_url' =>  'https://happyice-space.sgp1.digitaloceanspaces.com/payment-merchant/paynow.png',
        ]);

        PaymentMerchant::create([
            'name' => 'QRIS',
            'image_url' =>  'https://happyice-space.sgp1.digitaloceanspaces.com/payment-merchant/qris.png',
        ]);

        PaymentMerchant::create([
            'name' => 'ShopeePay',
            'image_url' =>  'https://happyice-space.sgp1.digitaloceanspaces.com/payment-merchant/shopeepay.png',
        ]);
    }
}
