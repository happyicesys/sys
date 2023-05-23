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
                    // temporary hardcode until android can give info
                    'type' => $vendOperatorPaymentGateway->paymentGateway->country->code == 'SG' ? 'paynow' :($vendOperatorPaymentGateway->paymentGateway->country->code == 'MY' ? 'duitnow_qr' : 'midtrans'),
                ]);
            }

            if(isset($response)) {
                $qrCodeText = '';
                $qrCodeUrl = '';
                $errorMsg = '';
                $isCreateInput = false;
                $isResizeImage = false;
                $isRequiredDecode = false;
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
                        $isRequiredDecode = true;
                        break;
                    case 'omise':
                        if((isset($response['source']['flow']) and $response['source']['flow'] == 'offline' and isset($response['source']['scannable_code']['image']['download_uri'])) or (isset($response['source']['flow']) and $response['source']['flow'] == 'redirect' and isset($response['authorize_uri']))) {
                            $isCreateInput = true;
                            if($response['source']['flow'] == 'offline') {
                                $qrCodeUrl = $response['source']['scannable_code']['image']['download_uri'];
                                $isRequiredDecode = true;
                            }else if($response['source']['flow'] == 'redirect') {
                                $qrCodeUrl = $response['authorize_uri'];
                                $isRequiredDecode = false;
                            }

                        }else if(isset($response['code']) and isset($response['message'])) {
                            $errorMsg .= 'Error: ';
                            $errorMsg .= $response['code'].' '.$response['message'];
                        }
                        break;
                }



                $img = false;
                if($isRequiredDecode) {
                    if($isResizeImage) {
                        $image = Image::make($qrCodeUrl)->resize(150, 150);
                        $img = Storage::put('/qr-code/'.$orderId.'.png', $image->stream()->__toString(), 'public');
                    }else {
                        $img = Storage::put('/qr-code/'.$orderId.'.png', file_get_contents($qrCodeUrl), 'public');
                    }
                    $url = Storage::url('/qr-code/'.$orderId.'.png');

                    $qrCodeReader = new QrReader($url);
                    $qrCodeText = $qrCodeReader->text([
                        'POSSIBLE_FORMATS' => 'QR_CODE',
                    ]);
                    Storage::disk('public')->delete('/qr-code/'.$orderId.'.png');
                }else {
                    switch($vendOperatorPaymentGateway->paymentGateway->name) {
                        case 'omise':
                            $htmlString = Http::get($qrCodeUrl)->body();

                            $doc = new \DOMDocument;
                            $doc->loadHTML($htmlString);
                            $xpath = new \DOMXpath($doc);
                            $val= $xpath->query('//input[@type="hidden" and @name = "qr_data"]/@value'
                            );
                            $qrCodeText = $val[0]->nodeValue;
                            break;
                    }
                }

                if($isCreateInput) {
                    PaymentGatewayLog::create([
                        'request' => $this->input,
                        'response' => $response,
                        'history_json' => $response,
                        'order_id' => $orderId,
                        'amount' => $amount,
                        'qr_url' => $qrCodeUrl,
                        'qr_text' => $qrCodeText,
                        'operator_payment_gateway_id' => $vendOperatorPaymentGateway->id,
                        'payment_gateway_id' => $vendOperatorPaymentGateway->paymentGateway->id,
                        'status' => PaymentGatewayLog::STATUS_PENDING,
                    ]);
                }

                $encodeMsg = base64_encode('QRCODE'.$qrCodeText.','.$orderId);
                $this->mqttService->publish('CM'.$vend->code, $originalInput['f'].','.strlen($encodeMsg).','.$encodeMsg);

                if(isset($errorMsg)) {
                    $this->mqttService->publish('CM'.$vend->code, $errorMsg);
                }

            }else {
                if(isset($errorMsg)) {
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
