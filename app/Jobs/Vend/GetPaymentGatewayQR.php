<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Models\PaymentGatewayLog;
use App\Models\VendData;
use App\Services\MqttService;
use App\Services\PaymentGatewayService;
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
        $vendChannel = $vend->vendChannels()->where('code', $input['SId'])->first();
        if($vendChannel) {
            $operatorTimezone = 'Asia/Singapore';
            if($vend->operators()->exists()) {
              $operatorTimezone = $vend->operators()->first()->timezone;
            }

            $vendCode = sprintf('%05d', $vend->code);
            $orderId = Carbon::now()->setTimeZone($operatorTimezone)->format('ymdhis').$vendCode;
            $response = $this->paymentGatewayService->createPaymentQrText($vend, [
                'request' => $this->input,
                'amount' => $input['PRICE'] * 100,
                'expiry_seconds' => isset($input['expiry_seconds']) ? $input['expiry_seconds'] : null,
                'metadata' => [
                    'order_id' => $orderId
                ],
            ]);

            if($response['errorMsg']) {
                $this->mqttService->publish('CM'.$vend->code, $errorMsg);
            }

            if($response['paymentGatewayLog']) {
                $encodeMsg = base64_encode('QRCODE'.$response['paymentGatewayLog']->qr_text.','.$orderId);
                $this->mqttService->publish('CM'.$vend->code, $originalInput['f'].','.strlen($encodeMsg).','.$encodeMsg);
            }

        }else {
            $this->mqttService->publish('CM'.$vend->code, 'This vending channel is not available');
            throw new \Exception('This vending channel is not available', 404);
        }
    }
}
