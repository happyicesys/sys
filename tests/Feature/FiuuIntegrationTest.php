<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGateway;
use App\Models\PaymentGateways\Fiuu;
use App\Models\Vend;
use App\Services\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FiuuIntegrationTest extends TestCase
{
    /**
     * Test Fiuu payment gateway initialization.
     */
    public function test_fiuu_gateway_can_be_initialized(): void
    {
        $fiuu = new Fiuu(
            'SB_happyice',
            '4c068d251b2f6678e4d12f7be5f253cb',
            'eb1a55fc4bdf5e67545b9db5b20d12f4',
            true // sandbox mode
        );

        $this->assertInstanceOf(Fiuu::class, $fiuu);
    }

    /**
     * Test vcode generation.
     */
    public function test_fiuu_vcode_generation(): void
    {
        $fiuu = new Fiuu(
            'SB_happyice',
            '4c068d251b2f6678e4d12f7be5f253cb',
            'eb1a55fc4bdf5e67545b9db5b20d12f4',
            true
        );

        $reflection = new \ReflectionClass($fiuu);
        $method = $reflection->getMethod('generateVCode');
        $method->setAccessible(true);

        $vcode = $method->invoke($fiuu, '10.00', 'TEST-ORDER-123', 'MYR');

        $this->assertNotEmpty($vcode);
        $this->assertEquals(32, strlen($vcode)); // MD5 hash is 32 characters
    }

    /**
     * Test payload building.
     */
    public function test_fiuu_payload_building(): void
    {
        $fiuu = new Fiuu(
            'SB_happyice',
            '4c068d251b2f6678e4d12f7be5f253cb',
            'eb1a55fc4bdf5e67545b9db5b20d12f4',
            true
        );

        $reflection = new \ReflectionClass($fiuu);
        $method = $reflection->getMethod('buildPayload');
        $method->setAccessible(true);

        $params = [
            'amount' => 10.50,
            'order_id' => 'TEST-ORDER-123',
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'customer_phone' => '60123456789',
            'description' => 'Test Purchase',
            'currency' => 'MYR',
            'country' => 'MY',
            'channel_code' => 'RPP_DuitNowQR',
        ];

        $payload = $method->invoke($fiuu, $params);

        $this->assertEquals('10.50', $payload['amount']);
        $this->assertEquals('TEST-ORDER-123', $payload['orderid']);
        $this->assertEquals('Test Customer', $payload['bill_name']);
        $this->assertEquals('MYR', $payload['currency']);
        $this->assertArrayHasKey('vcode', $payload);
    }

    /**
     * Test endpoint building for sandbox.
     */
    public function test_fiuu_sandbox_endpoint_building(): void
    {
        $fiuu = new Fiuu(
            'SB_happyice',
            '4c068d251b2f6678e4d12f7be5f253cb',
            'eb1a55fc4bdf5e67545b9db5b20d12f4',
            true // sandbox
        );

        $reflection = new \ReflectionClass($fiuu);
        $method = $reflection->getMethod('buildEndpoint');
        $method->setAccessible(true);

        $endpoint = $method->invoke($fiuu, null);

        $this->assertStringContainsString('sandbox-payment.fiuu.com', $endpoint);
        $this->assertStringContainsString('SB_happyice', $endpoint);
    }

    /**
     * Test endpoint building for production.
     */
    public function test_fiuu_production_endpoint_building(): void
    {
        $fiuu = new Fiuu(
            'M12345',
            'verify_key',
            'secret_key',
            false // production
        );

        $reflection = new \ReflectionClass($fiuu);
        $method = $reflection->getMethod('buildEndpoint');
        $method->setAccessible(true);

        $endpoint = $method->invoke($fiuu, null);

        $this->assertStringContainsString('pay.fiuu.com', $endpoint);
        $this->assertStringNotContainsString('sandbox', $endpoint);
    }

    /**
     * Test callback signature verification.
     */
    public function test_fiuu_callback_verification(): void
    {
        $fiuu = new Fiuu(
            'SB_happyice',
            '4c068d251b2f6678e4d12f7be5f253cb',
            'eb1a55fc4bdf5e67545b9db5b20d12f4',
            true
        );

        // Simulate a callback payload
        $txnID = 'TXN123456';
        $orderId = 'ORDER123';
        $status = '00';
        $amount = '10.00';
        $currency = 'MYR';
        $paydate = '2024-01-01 12:00:00';
        $appcode = 'APP123';

        // Generate expected skey
        $preSkey = md5($txnID . $orderId . $status . 'SB_happyice' . $amount . $currency);
        $skey = md5($paydate . 'SB_happyice' . $preSkey . $appcode . 'eb1a55fc4bdf5e67545b9db5b20d12f4');

        $payload = [
            'txnID' => $txnID,
            'orderid' => $orderId,
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
            'paydate' => $paydate,
            'appcode' => $appcode,
            'skey' => $skey,
        ];

        $result = $fiuu->verifyResponse($payload);

        $this->assertTrue($result);
    }

    /**
     * Test available channel codes.
     */
    public function test_fiuu_available_channels(): void
    {
        $channels = Fiuu::availableChannelCodes();

        $this->assertArrayHasKey('SG', $channels);
        $this->assertArrayHasKey('MY', $channels);
        $this->assertArrayHasKey('ID', $channels);

        $this->assertContains('PayNow', $channels['SG']);
        $this->assertContains('RPP_DuitNowQR', $channels['MY']);
        $this->assertContains('e2Pay_DANA', $channels['ID']);
    }

    /**
     * Test payment method mapping.
     */
    public function test_fiuu_payment_method_mapping(): void
    {
        $this->assertEquals('PayNow', Fiuu::PAYMENT_METHOD_MAPPING[Fiuu::PAYMENT_METHOD_PAYNOW_SG]);
        $this->assertEquals('RPP_DuitNowQR', Fiuu::PAYMENT_METHOD_MAPPING[Fiuu::PAYMENT_METHOD_DUITNOW_MY]);
        $this->assertEquals('e2Pay_DANA', Fiuu::PAYMENT_METHOD_MAPPING[Fiuu::PAYMENT_METHOD_DANA_ID]);
    }
}
