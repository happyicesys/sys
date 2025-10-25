<?php

namespace Database\Seeders;

use App\Models\Operator;
use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class OperatorPaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        if (!config('app.debug')) {
            return;
        }

        $operators = Operator::all();
        if ($operators->isEmpty()) {
            return;
        }

        $fiuuGateway = PaymentGateway::firstOrCreate([
            'name' => 'fiuu',
        ], [
            'remarks' => 'Fiuu hosted payment gateway',
            'key1_name' => 'merchant_id',
            'key2_name' => 'verify_key',
            'key3_name' => 'secret_key',
        ]);

        $sandboxCredentials = [
            'merchant_id' => env('FIUU_SANDBOX_MERCHANT_ID', 'SB_happyice'),
            'verify_key' => env('FIUU_SANDBOX_VERIFY_KEY', 'sample_verify_key'),
            'secret_key' => env('FIUU_SANDBOX_SECRET_KEY', 'sample_secret_key'),
        ];

        $productionCredentials = [
            'merchant_id' => env('FIUU_MERCHANT_ID'),
            'verify_key' => env('FIUU_VERIFY_KEY'),
            'secret_key' => env('FIUU_SECRET_KEY'),
        ];

        foreach ($operators as $operator) {
            foreach ([
                OperatorPaymentGateway::TYPE_SANDBOX => $sandboxCredentials,
                OperatorPaymentGateway::TYPE_PRODUCTION => $productionCredentials,
            ] as $type => $credentials) {
                if (empty(array_filter($credentials, fn ($value) => filled($value)))) {
                    continue;
                }

                OperatorPaymentGateway::updateOrCreate(
                    [
                        'operator_id' => $operator->id,
                        'payment_gateway_id' => $fiuuGateway->id,
                        'type' => $type,
                    ],
                    [
                        'key1' => $credentials['merchant_id'],
                        'key2' => $credentials['verify_key'],
                        'key3' => $credentials['secret_key'],
                    ]
                );
            }
        }
    }
}
