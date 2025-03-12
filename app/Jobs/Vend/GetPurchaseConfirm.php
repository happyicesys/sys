<?php

namespace App\Jobs\Vend;

use App\Jobs\PublishMqtt;
use App\Models\DeliveryPlatformOrder;
use App\Models\DispenseRecord;
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

class GetPurchaseConfirm implements ShouldQueue
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
        $dispenseRecord = DispenseRecord::where('order_id', $this->orderId)->first();

        if($dispenseRecord) {
          $dispenseRecord->update([
            'is_vm_receive_dispense_signal' => true,
          ]);
        }

        if($deliveryPlatformOrder) {
          $deliveryPlatformOrder->update([
              'status' => DeliveryPlatformOrder::STATUS_DISPENSING > $deliveryPlatformOrder->status ? DeliveryPlatformOrder::STATUS_DISPENSING : $deliveryPlatformOrder->status,
              'status_json' => array_merge_recursive($deliveryPlatformOrder->status_json, [
                  'status' => DeliveryPlatformOrder::STATUS_MAPPING[DeliveryPlatformOrder::STATUS_DISPENSING],
                  'datetime' => Carbon::now()->toDateTimeString(),
              ]),
              'is_verified' => true,
          ]);
        }

        if($paymentGatewayLog) {
          Log::info('PaymentGatewayLog: '.$paymentGatewayLog->id . ',OrderID: '.$this->orderId);
          $paymentGatewayLog->update([
              'is_dispensed' => true,
          ]);
        }

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

          PublishMqtt::dispatch('CM'.$this->vend->code, $fid.','.$contentLength.','.$content.','.$md5)->onQueue('high');
          // $this->mqttService->publish('CM'.$this->vend->code, $fid.','.$contentLength.','.$content.','.$md5);
        }else {
          PublishMqtt::dispatch('CM'.$this->vend->code, 'This order id not found or QR is expired')->onQueue('high');
          // $this->mqttService->publish('CM'.$this->vend->code, 'This order id not found or QR is expired');
          throw new \Exception('This order id not found or QR is expired', 404);
        }
    }
}
