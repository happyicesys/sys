<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Ace Credits & Apex Credits" for the MINDEF operator (HIMD), Brian 2026-10-29:
 * shown only on /api/v1/payment-merchants/SG/omise/mindef (`menu_variant`).
 * It charges as plain Omise PayNow (slug `paynow`), like LifeSG and GrabPay.
 */
return new class extends Migration
{
    public function up(): void
    {
        $gatewayId = DB::table('payment_gateways')
            ->join('countries', 'countries.id', '=', 'payment_gateways.country_id')
            ->where('payment_gateways.name', 'omise')
            ->where('countries.code', 'SG')
            ->value('payment_gateways.id');

        // A fresh test/dev DB carries no gateways; the rows are prod data.
        if (! $gatewayId || DB::table('payment_methods')->where('code', 802)->exists()) {
            return;
        }

        $now = now();
        $merchantId = DB::table('payment_merchants')->insertGetId([
            'name' => 'Ace Credits & Apex Credits',
            'image_url' => 'https://sys.happyice.com.sg/img/payment-merchant/mindef-ace-apex.png',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('payment_methods')->insert([
            'code' => 802,
            'name' => 'Omise (MINDEF Ace / Apex Credits)',
            'type_name' => 'paynow',
            'menu_variant' => 'mindef',
            'sequence' => 0,
            'is_active' => true,
            'is_apk_constant' => false,
            'payment_gateway_id' => $gatewayId,
            'payment_merchant_id' => $merchantId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $merchantId = DB::table('payment_methods')->where('code', 802)->value('payment_merchant_id');
        DB::table('payment_methods')->where('code', 802)->delete();
        if ($merchantId) {
            DB::table('payment_merchants')->where('id', $merchantId)->delete();
        }
    }
};
