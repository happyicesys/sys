<?php

namespace App\Jobs\Vend;

use App\Models\PaymentGatewayLog;
use App\Services\MqttService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GetPurchaseConfirm
//implements ShouldQueue
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

        if($paymentGatewayLog) {
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
          $key = '123456789110138A';
          $md5 = md5($fid.','.$contentLength.','.$content.$key);

          $this->mqttService->publish('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5);
        }else {
            $this->mqttService->publish('CM'.$this->vend->code, 'This order id confirmation not found, please contact admin');
            throw new \Exception('This order id confirmation not found, please contact admin', 404);
        }
    }
}
