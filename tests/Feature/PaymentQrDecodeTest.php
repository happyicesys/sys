<?php

namespace Tests\Feature;

use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\Vend;
use App\Services\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Omise PayNow returns a QR IMAGE; mark1 reads the payment text out of it. A bad download or a
 * decoder crash (DivisionByZeroError on 2470, 2026-10-10) must never abort the REQQR uncaught nor
 * produce a blank QR: one retry, then an error the machine shows.
 */
class PaymentQrDecodeTest extends TestCase
{
    use RefreshDatabase;

    private function dataUri(string $png): string
    {
        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function blankPng(): string
    {
        $image = imagecreatetruecolor(200, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** The service with the gateway call stubbed to Omise's "offline" (scannable QR) answer. */
    private function serviceAnswering(string $downloadUri): PaymentGatewayService
    {
        $gateway = new PaymentGateway;
        $gateway->name = 'omise';
        $gateway->id = 7;
        $operatorGateway = new OperatorPaymentGateway;
        $operatorGateway->id = 9;
        $operatorGateway->setRelation('paymentGateway', $gateway);

        $service = Mockery::mock(PaymentGatewayService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('createPaymentRequest')->andReturn([
            'response' => ['source' => ['flow' => 'offline', 'scannable_code' => ['image' => ['download_uri' => $downloadUri]]]],
            'raw_response' => null,
            'operatorPaymentGateway' => $operatorGateway,
        ]);

        return $service;
    }

    private function request(Vend $vend): array
    {
        return [
            'request' => ['Type' => 'REQQR', 'PRICE' => 1.6, 'SId' => 11],
            'amount' => 1.6,
            'metadata' => ['order_id' => '26101020000002009', 'vend_id' => $vend->code, 'txn_src' => 1],
        ];
    }

    private function vend(): Vend
    {
        $vend = new Vend;
        $vend->code = 2009;

        return $vend;
    }

    public function test_a_readable_qr_is_recorded_with_its_text(): void
    {
        $png = file_get_contents(base_path('tests/Support/qr_hello_world.png'));

        $result = $this->serviceAnswering($this->dataUri($png))->createPaymentQrText($this->vend(), $this->request($this->vend()));

        $this->assertNull($result['errorMsg']);
        $this->assertSame('Hello world!', $result['paymentGatewayLog']->qr_text);
    }

    public function test_an_unreadable_qr_becomes_an_error_never_a_blank_qr(): void
    {
        $result = $this->serviceAnswering($this->dataUri($this->blankPng()))->createPaymentQrText($this->vend(), $this->request($this->vend()));

        $this->assertNull($result['paymentGatewayLog']);
        $this->assertSame('Error: QR code could not be read, please try again', $result['errorMsg']);
        $this->assertSame(0, PaymentGatewayLog::count());
    }

    public function test_a_corrupt_image_is_caught_not_thrown(): void
    {
        $result = $this->serviceAnswering($this->dataUri('not a png at all'))->createPaymentQrText($this->vend(), $this->request($this->vend()));

        $this->assertNull($result['paymentGatewayLog']);
        $this->assertStringStartsWith('Error: QR code could not be read', $result['errorMsg']);
    }

    public function test_a_dead_download_fails_fast_and_is_retried_once(): void
    {
        $started = microtime(true);

        // Port 9 (discard) refuses at once; both attempts fail without waiting on the 60 s default.
        $result = $this->serviceAnswering('http://127.0.0.1:9/qr.png')->createPaymentQrText($this->vend(), $this->request($this->vend()));

        $this->assertNull($result['paymentGatewayLog']);
        $this->assertNotEmpty($result['errorMsg']);
        $this->assertLessThan(11, microtime(true) - $started);
    }
}
