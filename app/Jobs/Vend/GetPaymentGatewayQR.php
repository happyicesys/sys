<?php

namespace App\Jobs\Vend;

use App\Jobs\PublishMqtt;
use App\Models\Vend;
use App\Services\Mqtt\MqttPublisher;
use App\Services\PaymentGatewayService;
use App\Services\RunningNumberService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GetPaymentGatewayQR
// implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $originalInput;

    protected $input;

    protected $vend;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($originalInput, $input, Vend $vend)
    {
        $this->originalInput = $originalInput;
        $this->input = $input;
        $this->vend = $vend;
    }

    /**
     * Execute the job. Services are resolved here rather than built in the
     * constructor and carried on the job.
     *
     * Not ShouldQueue: it runs inside the MQTT subscriber as the REQQR arrives,
     * and replies inline on the process's persistent publisher. The reply used
     * to go through a queued PublishMqtt on `high`, where it waited 1.7 s
     * (p50, max 2.9 s) for an idle worker's 3 s poll — most of the 2–4 s a
     * customer waited for the QR (2009, 2026-10-10).
     *
     * @return void
     */
    public function handle(PaymentGatewayService $paymentGatewayService, RunningNumberService $runningNumberService, MqttPublisher $publisher)
    {
        $originalInput = $this->originalInput;
        $vend = $this->vend;
        $input = $this->input;
        // $vendChannel = $vend->vendChannels()->where('code', $input['SId'])->first();
        // if($vendChannel) {
        $orderId = $runningNumberService->getVendOrderID($vend);
        $response = $paymentGatewayService->createPaymentQrText($vend, [
            'request' => $this->input,
            'amount' => $input['PRICE'],
            'expiry_seconds' => isset($input['expiry_seconds']) ? $input['expiry_seconds'] : null,
            'type' => isset($input['payment_gateway_slug']) ? $input['payment_gateway_slug'] : null,
            'metadata' => [
                'order_id' => $orderId,
                'vend_id' => $vend->code,
                'cust_id' => $vend->customer ? $vend->customer->refID : null,
                'cust_name' => $vend->customer && $vend->customer->person_id ?
                        $vend->customer->virtual_customer_prefix.'-'.$vend->customer->virtual_customer_code.' '.$vend->customer->name : null,
                'txn_src' => isset($input['txn_src']) ? $input['txn_src'] : null,
            ],
        ]);

        if ($response['errorMsg']) {
            $this->reply($publisher, 'CM'.$vend->code, $response['errorMsg']);
        }

        if ($response['paymentGatewayLog']) {
            $encodeMsg = base64_encode('QRCODE'.$response['paymentGatewayLog']->qr_text.','.$orderId);
            $this->reply($publisher, 'CM'.$vend->code, $originalInput['f'].','.strlen($encodeMsg).','.$encodeMsg);
        }

        // }else {
        //     $this->mqttService->publish('CM'.$vend->code, 'This vending channel is not available');
        //     throw new \Exception('This vending channel is not available', 404);
        // }
    }

    /**
     * Sends the reply now; if the inline publish fails (the publisher already
     * reconnected and retried once), hands it to the queue so the customer
     * still gets it, only later.
     */
    private function reply(MqttPublisher $publisher, string $topic, string $message): void
    {
        try {
            $publisher->publish($topic, $message);
        } catch (Throwable $e) {
            Log::warning('QR reply: inline publish failed, queueing it', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
            PublishMqtt::dispatch($topic, $message)->onQueue('high');
        }
    }
}
