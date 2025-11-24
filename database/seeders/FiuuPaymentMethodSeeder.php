<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\PaymentGateway;
use App\Models\PaymentGateways\Fiuu;
use App\Models\PaymentMerchant;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class FiuuPaymentMethodSeeder extends Seeder
{
    /**
     * Seed Fiuu payment methods for Singapore, Malaysia, and Indonesia.
     */
    public function run(): void
    {
        // Get or create payment merchants
        $merchants = $this->createPaymentMerchants();

        // Get Fiuu payment gateway for each country
        $sgFiuu = PaymentGateway::where('name', 'fiuu')
            ->whereHas('country', fn($q) => $q->where('code', 'SG'))
            ->first();

        $myFiuu = PaymentGateway::where('name', 'fiuu')
            ->whereHas('country', fn($q) => $q->where('code', 'MY'))
            ->first();

        $idFiuu = PaymentGateway::where('name', 'fiuu')
            ->whereHas('country', fn($q) => $q->where('code', 'ID'))
            ->first();

        // If gateways don't exist, create them
        if (!$sgFiuu) {
            $sgCountry = Country::where('code', 'SG')->first();
            if ($sgCountry) {
                $sgFiuu = PaymentGateway::create([
                    'name' => 'fiuu',
                    'country_id' => $sgCountry->id,
                    'key1_name' => 'merchant_id',
                    'key2_name' => 'verify_key',
                    'key3_name' => 'secret_key',
                    'remarks' => 'Fiuu payment gateway for Singapore',
                ]);
            }
        }

        if (!$myFiuu) {
            $myCountry = Country::where('code', 'MY')->first();
            if ($myCountry) {
                $myFiuu = PaymentGateway::create([
                    'name' => 'fiuu',
                    'country_id' => $myCountry->id,
                    'key1_name' => 'merchant_id',
                    'key2_name' => 'verify_key',
                    'key3_name' => 'secret_key',
                    'remarks' => 'Fiuu payment gateway for Malaysia',
                ]);
            }
        }

        if (!$idFiuu) {
            $idCountry = Country::where('code', 'ID')->first();
            if ($idCountry) {
                $idFiuu = PaymentGateway::create([
                    'name' => 'fiuu',
                    'country_id' => $idCountry->id,
                    'key1_name' => 'merchant_id',
                    'key2_name' => 'verify_key',
                    'key3_name' => 'secret_key',
                    'remarks' => 'Fiuu payment gateway for Indonesia',
                ]);
            }
        }

        // Singapore Payment Methods
        if ($sgFiuu) {
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_GRABPAY_SG, 'GrabPay (SG)', 'grabpay', $sgFiuu->id, $merchants['grabpay']->id ?? null, 1);
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_PAYNOW_SG, 'PayNow', 'PayNow', $sgFiuu->id, $merchants['paynow']->id ?? null, 2);
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_AXS_SG, 'AXS Kiosk', 'AXS', $sgFiuu->id, $merchants['axs']->id ?? null, 3);
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_SINGPOST_SG, 'SingPost SAM / eNETS', 'singpost', $sgFiuu->id, $merchants['singpost']->id ?? null, 4);
        }

        // Malaysia Payment Methods
        if ($myFiuu) {
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_FPX_MY, 'FPX (All Banks)', 'fpx', $myFiuu->id, $merchants['fpx']->id ?? null, 1);
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_DUITNOW_MY, 'DuitNow QR', 'RPP_DuitNowQR', $myFiuu->id, $merchants['duitnow']->id ?? null, 2);
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_TNG_MY, 'Touch \'n Go eWallet', 'TNG-EWALLET', $myFiuu->id, $merchants['tng']->id ?? null, 3);
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_GRABPAY_MY, 'GrabPay (MY)', 'GrabPay', $myFiuu->id, $merchants['grabpay']->id ?? null, 4);
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_SHOPEEPAY_MY, 'ShopeePay (MY)', 'ShopeePay', $myFiuu->id, $merchants['shopeepay']->id ?? null, 5);
        }


        // Indonesia Payment Methods
        if ($idFiuu) {
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_DANA_ID, 'DANA', 'e2Pay_DANA', $idFiuu->id, $merchants['dana']->id ?? null, 1);
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_SHOPEEPAY_ID, 'ShopeePay QRIS', 'e2Pay_SHOPEEPAY_QRIS', $idFiuu->id, $merchants['shopeepay']->id ?? null, 2);
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_GOPAY_ID, 'GoPay', 'e2Pay_GOPAY', $idFiuu->id, $merchants['gopay']->id ?? null, 3);
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_CIMB_QRIS_ID, 'CIMB QRIS', 'e2Pay_CIMB_QRIS', $idFiuu->id, $merchants['cimb']->id ?? null, 4);
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_KREDIVO_ID, 'Kredivo Financing', 'e2Pay_Kredivo_FN', $idFiuu->id, $merchants['kredivo']->id ?? null, 5);
            $this->createPaymentMethod(Fiuu::PAYMENT_METHOD_INDODANA_ID, 'Indodana Financing', 'e2Pay_Indodana_FN', $idFiuu->id, $merchants['indodana']->id ?? null, 6);
        }
    }

    private function createPaymentMerchants(): array
    {
        $merchants = [];

        $merchantData = [
            'grabpay' => ['name' => 'GrabPay', 'image_url' => null],
            'paynow' => ['name' => 'PayNow', 'image_url' => null],
            'axs' => ['name' => 'AXS', 'image_url' => null],
            'singpost' => ['name' => 'SingPost', 'image_url' => null],
            'fpx' => ['name' => 'FPX', 'image_url' => null],
            'duitnow' => ['name' => 'DuitNow', 'image_url' => null],
            'tng' => ['name' => 'Touch \'n Go', 'image_url' => null],
            'shopeepay' => ['name' => 'ShopeePay', 'image_url' => null],
            'dana' => ['name' => 'DANA', 'image_url' => null],
            'gopay' => ['name' => 'GoPay', 'image_url' => null],
            'cimb' => ['name' => 'CIMB', 'image_url' => null],
            'kredivo' => ['name' => 'Kredivo', 'image_url' => null],
            'indodana' => ['name' => 'Indodana', 'image_url' => null],
        ];

        foreach ($merchantData as $slug => $data) {
            $merchants[$slug] = PaymentMerchant::firstOrCreate(
                ['name' => $data['name']],
                ['image_url' => $data['image_url']]
            );
        }

        return $merchants;
    }

    private function createPaymentMethod(
        int $code,
        string $name,
        string $typeName,
        int $paymentGatewayId,
        ?int $paymentMerchantId,
        int $sequence
    ): void {
        PaymentMethod::updateOrCreate(
            [
                'code' => $code,
                'payment_gateway_id' => $paymentGatewayId,
            ],
            [
                'name' => $name,
                'type_name' => $typeName,
                'payment_merchant_id' => $paymentMerchantId,
                'sequence' => $sequence,
                'is_active' => true,
                'is_apk_constant' => true,
            ]
        );
    }
}
