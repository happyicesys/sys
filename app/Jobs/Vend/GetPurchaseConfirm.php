<?php

namespace App\Jobs\Vend;

use App\Models\DeliveryPlatformOrder;
use App\Models\PaymentGatewayLog;
use App\Services\MqttService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GetPurchaseConfirm
// implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $orderId;
    protected $vend;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($orderId, $vend)
    {
        $this->orderId = $orderId;
        $this->vend = $vend;
        $this->mqttService = new MqttService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $paymentGatewayLog = PaymentGatewayLog::where('order_id', $this->orderId)->where('status', 2)->first();
        $deliveryPlatformOrder = DeliveryPlatformOrder::where('vend_transaction_order_id', $this->orderId)->first();

        if($paymentGatewayLog or $deliveryPlatformOrder) {
          $result = [
            'Type' => 'CONFIRM',
            'orderid' => $this->orderId,
            'code' => 1,
            'des' => null,
            'time' => Carbon::now()->timestamp,
          ];

          $fid = $this->orderId;
          $content = base64_encode(json_encode($result));
          $contentLength = strlen($content);
          $key = $this->vend && $this->vend->private_key ? $this->vend->private_key : '123456789110138A';
          $md5 = md5($fid.','.$contentLength.','.$content.$key);

          $this->mqttService->publish('CM'.$this->vend->code, $fid.','.$contentLength.','.$content.','.$md5);
        }else {
            $this->mqttService->publish('CM'.$this->vend->code, 'This order id not found or QR is expired');
            throw new \Exception('This order id not found or QR is expired', 404);
        }
    }
}
