<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Models\PaymentGatewayLog;
use App\Models\VendData;
use App\Services\MqttService;
use App\Services\PaymentGatewayService;
use App\Services\RunningNumberService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;



class GetPaymentGatewayQR
//implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $originalInput;
    protected $input;
    protected $vend;
    protected $paymentGatewayService;
    protected $runningNumberService;
    protected $mqttService;
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
        $this->paymentGatewayService = new PaymentGatewayService();
        $this->runningNumberService = new RunningNumberService();
        $this->mqttService = new MqttService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $originalInput = $this->originalInput;
        $vend = $this->vend;
        $input = $this->input;
        // $vendChannel = $vend->vendChannels()->where('code', $input['SId'])->first();
        // if($vendChannel) {
            $orderId = $this->runningNumberService->getVendOrderID($vend);

            $response = $this->paymentGatewayService->createPaymentQrText($vend, [
                'request' => $this->input,
                'amount' => $input['PRICE'],
                'expiry_seconds' => isset($input['expiry_seconds']) ? $input['expiry_seconds'] : null,
                'type' => isset($input['payment_gateway_slug']) ? $input['payment_gateway_slug'] : null,
                'metadata' => [
                    'order_id' => $orderId,
                    'vend_code' => $vend->code,
                    'customer_code' => $vend->customer->exists() ?
                        $vend->customer->code : null,
                ],
            ]);

            if($response['errorMsg']) {
                $this->mqttService->publish('CM'.$vend->code, $errorMsg);
            }

            if($response['paymentGatewayLog']) {
                $encodeMsg = base64_encode('QRCODE'.$response['paymentGatewayLog']->qr_text.','.$orderId);
                $this->mqttService->publish('CM'.$vend->code, $originalInput['f'].','.strlen($encodeMsg).','.$encodeMsg);
            }

        // }else {
        //     $this->mqttService->publish('CM'.$vend->code, 'This vending channel is not available');
        //     throw new \Exception('This vending channel is not available', 404);
        // }
    }
}
