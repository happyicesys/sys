<?php

namespace Database\Seeders;

use App\Models\OperatorPaymentGateway;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IndoMidtransSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        OperatorPaymentGateway::create([
            'operator_id' => 8,
            'payment_gateway_id' => 1,
            'key1' => 'SB-Mid-server-USybf0_T3EbRgMHnibomesGv',
            'key2' => 'SB-Mid-client-FmaI0dG1j09oCvs0',
            'key3' => 'G818547059',
            'type' => 'sandbox',
        ]);

        OperatorPaymentGateway::create([
            'operator_id' => 8,
            'payment_gateway_id' => 1,
            'key1' => 'Mid-server-w4POgfA3U1kyaXi_lafzNSXY',
            'key2' => 'Mid-client-kFCRRbSOwNBg3FFs',
            'key3' => 'G818547059',
            'type' => 'production',
        ]);
    }
}
