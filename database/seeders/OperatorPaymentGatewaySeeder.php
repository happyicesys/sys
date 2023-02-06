<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Operator;
use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGateway;
use App\Models\PaymentGateway\Midtrans;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OperatorPaymentGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $country = Country::create([
            'code' => 'ID',
            'name' => 'Indonesia',
            'phone_code' => 62,
            'sequence' => 5,
            'currency_name' => 'IDR',
            'currency_symbol' => 'Rp',
        ]);

        $operator = Operator::create([
            'code' => 'ID',
            'name' => 'Indonesia',
            'timezone' => 'Asia/Jakarta',
            'country_id' => $country->id,
        ]);

        $paymentGateway = PaymentGateway::create([
            'name' => 'midtrans',
            'classname' => get_class(new Midtrans()),
        ]);

        OperatorPaymentGateway::create([
            'operator_id' => $operator->id,
            'payment_gateway_id' => $paymentGateway->id,
            'key1' => 'SB-Mid-server-USybf0_T3EbRgMHnibomesGv',
            'key2' => 'SB-Mid-client-FmaI0dG1j09oCvs0',
            'type' => 'sandbox',
        ]);

        OperatorPaymentGateway::create([
            'operator_id' => $operator->id,
            'payment_gateway_id' => $paymentGateway->id,
            'key1' => 'Mid-server-w4POgfA3U1kyaXi_lafzNSXY',
            'key2' => 'Mid-client-kFCRRbSOwNBg3FFs',
            'type' => 'production',
        ]);
    }
}
