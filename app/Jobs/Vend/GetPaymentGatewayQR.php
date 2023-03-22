<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Models\PaymentGatewayLog;
use App\Services\MqttService;
use App\Services\PaymentGatewayService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\VendData;

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
            $amount = $input['PRICE'];
            $expirySeconds = isset($input['expiry_seconds']) ? $input['expiry_seconds'] : 150;

            $vendOperatorPaymentGateway = $this->paymentGatewayService->getOperatorPaymentGateway($vend);

            if($vendOperatorPaymentGateway) {
                $response = $this->paymentGatewayService->create($vendOperatorPaymentGateway, [
                    'orderId' => $orderId,
                    'amount' => $amount,
                    'tz' => $operatorTimezone,
                    'expiry_seconds' => $expirySeconds,
                ]);
            }
            // dd($response);

            if(isset($response) and isset($response['actions']) and isset($response['actions'][0]['url'])) {
                switch($vendOperatorPaymentGateway->paymentGateway->name) {
                    case 'midtrans':
                        break;
                    case 'omise':
                        break;
                }
                PaymentGatewayLog::create([
                    'request' => $this->input,
                    'response' => $response,
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'status' => PaymentGatewayLog::STATUS_PENDING,
                ]);
                $encodeMsg = base64_encode('QRCODE'.$response['actions'][0]['url'].','.$orderId);
                $this->mqttService->publish('CM'.$vend->code, $originalInput['f'].','.strlen($encodeMsg).','.$encodeMsg);
            }else {
                if(isset($response['validation_messages'])) {
                    $this->mqttService->publish('CM'.$vend->code, 'Error: '.$response['validation_messages'][0]);
                }else if(isset($response['status_message']) and isset($response['status_code'])) {
                    $this->mqttService->publish('CM'.$vend->code, 'Error: '.$response['status_message'].' ('.$response['status_code'].')');
                }else{
                    $this->mqttService->publish('CM'.$vend->code, 'Error: Api key not set or parameters error');
                }
                throw new \Exception('Api key not set or parameters error', 404);
            }
        }else {
            $this->mqttService->publish('CM'.$vend->code, 'This vending channel is not available');
            throw new \Exception('This vending channel is not available', 404);
        }

    }
}
