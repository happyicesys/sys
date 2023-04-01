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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Image;
use Zxing\QrReader;


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
                    'currency' => $vendOperatorPaymentGateway->paymentGateway->country->currency_name,
                ]);
            }

            if(isset($response)) {

                $qrCodeUrl = '';
                $errorMsg = '';
                $isCreateInput = false;
                $isResizeImage = false;
                switch($vendOperatorPaymentGateway->paymentGateway->name) {
                    case 'midtrans':
                        if(isset($response['actions']) and isset($response['actions'][0]['url'])) {
                            $isCreateInput = true;
                            $qrCodeUrl = $response['actions'][0]['url'];
                        }else if(isset($response['validation_messages']) or isset($response['status_message'])) {
                            $errorMsg .= 'Error: ';
                            $errorMsg .= isset($response['validation_messages']) ? $response['validation_messages'][0] : $response['status_message'];
                        }
                        $isResizeImage = true;
                        break;
                    case 'omise':
                        if(isset($response['source']['scannable_code']['image']['download_uri'])) {
                            $isCreateInput = true;
                            $qrCodeUrl = $response['source']['scannable_code']['image']['download_uri'];
                        }else if(isset($response['code']) and isset($response['message'])) {
                            $errorMsg .= 'Error: ';
                            $errorMsg .= $response['code'].' '.$response['message'];
                        }
                        break;
                }

                if($isCreateInput) {
                    PaymentGatewayLog::create([
                        'request' => $this->input,
                        'response' => $response,
                        'order_id' => $orderId,
                        'amount' => $amount,
                        'payment_gateway_id' => $vendOperatorPaymentGateway->paymentGateway->id,
                        'status' => PaymentGatewayLog::STATUS_PENDING,
                    ]);
                }

                $img =  false;
                if($isResizeImage) {
                    $image = Image::make($qrCodeUrl)->resize(150, 150);
                    $img = Storage::put('/qr-code/'.$orderId.'.png', $image->stream()->__toString(), 'public');
                }else {
                    $img = Storage::put('/qr-code/'.$orderId.'.png', file_get_contents($qrCodeUrl), 'public');
                }

                $url = Storage::url('/qr-code/'.$orderId.'.png');

                $qrCodeReader = new QrReader($url);
                $qrCodeText = $qrCodeReader->text();

                Storage::disk('public')->delete('/qr-code/'.$orderId.'.png');

                $encodeMsg = base64_encode('QRCODE'.$qrCodeText.','.$orderId);
                $this->mqttService->publish('CM'.$vend->code, $originalInput['f'].','.strlen($encodeMsg).','.$encodeMsg);

            }else {
                if($errorMsg) {
                    $this->mqttService->publish('CM'.$vend->code, $errorMsg);
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
