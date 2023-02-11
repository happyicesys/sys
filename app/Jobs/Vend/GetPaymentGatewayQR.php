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

        $orderId = Carbon::now()->format('ymdhis');
        $amount = $input['PRICE'];


        $response = $this->paymentGatewayService->create('midtrans', $vend, [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount,
            ],
            'qris' => [
                'acquirer' => 'gopay',
            ],
        ]);

        // MQTT::publish('CV1', 'done');
        if($response and isset($response['actions']) and isset($response['actions'][0]['url'])) {
            PaymentGatewayLog::create([
                'request' => $this->input,
                'response' => $response,
                'order_id' => $orderId,
                'amount' => $amount,
                'status' => PaymentGatewayLog::STATUS_PENDING,
            ]);
            $encodeMsg = base64_encode('ORCODE'.$response['actions'][0]['url'].','.$orderId);
            $this->mqttService->publish('CV1', $originalInput['f'].','.strlen($encodeMsg).','.$encodeMsg);
            return true;
        }else {
            return false;
        }
    }
}
