<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Jobs\Vend\GetPaymentGatewayQR;
use App\Models\Vend;
use App\Services\PaymentGatewayService;
use App\Services\RunningNumberService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PhpMqtt\Client\Exceptions\MqttClientException;
use Tests\Support\FakeMqttPublisher;
use Tests\TestCase;

/**
 * The QR answer to a REQQR is published inline by the job, not through a queued
 * PublishMqtt: the queue hop cost a customer 1.7 s (p50) on every QR (2026-10-10).
 */
class GetPaymentGatewayQrReplyTest extends TestCase
{
    private function job(): GetPaymentGatewayQR
    {
        $vend = new Vend;
        $vend->code = 2009;

        return new GetPaymentGatewayQR(['f' => '17'], ['PRICE' => 1.6], $vend);
    }

    private function orderIds(): RunningNumberService
    {
        $numbers = Mockery::mock(RunningNumberService::class);
        $numbers->shouldReceive('getVendOrderID')->andReturn('26101011522402009');

        return $numbers;
    }

    private function gateway(array $response): PaymentGatewayService
    {
        $gateway = Mockery::mock(PaymentGatewayService::class);
        $gateway->shouldReceive('createPaymentQrText')->once()->andReturn($response);

        return $gateway;
    }

    private function qrFrame(): string
    {
        $encoded = base64_encode('QRCODE00020101021226580009SG.PAYNOW,26101011522402009');

        return '17,'.strlen($encoded).','.$encoded;
    }

    public function test_the_qr_is_published_at_once_without_a_queued_job(): void
    {
        Queue::fake();

        $this->job()->handle(
            $this->gateway(['errorMsg' => '', 'paymentGatewayLog' => (object) ['qr_text' => '00020101021226580009SG.PAYNOW']]),
            $this->orderIds(),
            $this->mqtt,
        );

        $sent = $this->mqtt->publishedTo('CM2009');
        $this->assertCount(1, $sent);
        $this->assertSame($this->qrFrame(), $sent[0]['message']);
        $this->assertSame(1, $sent[0]['qos']);
        Queue::assertNotPushed(PublishMqtt::class);
    }

    public function test_a_gateway_error_is_also_answered_at_once(): void
    {
        Queue::fake();

        $this->job()->handle(
            $this->gateway(['errorMsg' => 'Error: invalid_amount', 'paymentGatewayLog' => null]),
            $this->orderIds(),
            $this->mqtt,
        );

        $this->assertSame(['Error: invalid_amount'], array_column($this->mqtt->publishedTo('CM2009'), 'message'));
        Queue::assertNotPushed(PublishMqtt::class);
    }

    public function test_a_failed_inline_publish_falls_back_to_the_high_queue(): void
    {
        Queue::fake();
        $broken = new class extends FakeMqttPublisher
        {
            public function publish(string $topic, string $message, int $qos = 1, ?string $connection = null): void
            {
                throw new MqttClientException('broker unreachable');
            }
        };

        $this->job()->handle(
            $this->gateway(['errorMsg' => '', 'paymentGatewayLog' => (object) ['qr_text' => '00020101021226580009SG.PAYNOW']]),
            $this->orderIds(),
            $broken,
        );

        Queue::assertPushedOn('high', PublishMqtt::class, function (PublishMqtt $job) {
            $read = fn (string $name) => (new \ReflectionProperty($job, $name))->getValue($job);

            return $read('topic') === 'CM2009' && $read('message') === $this->qrFrame() && $read('qos') === 1;
        });
    }
}
