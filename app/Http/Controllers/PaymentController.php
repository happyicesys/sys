<?php

namespace App\Http\Controllers;

use App\Jobs\PublishMqtt;
use App\Jobs\RefundOmiseJob;
use App\Jobs\Vend\LogNofoundTxnIfStillMissing;
use App\Models\Country;
use App\Models\PaymentGateways\Fiuu;
use App\Models\PaymentGateways\Midtrans;
use App\Models\PaymentGateways\Omise;
use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendData;
use App\Services\MqttService;
use App\Services\PaymentGatewayService;
use App\Services\VendDataService;
use App\Services\VendDispenseService;
use App\Services\VendTransactionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{

  protected $mqttService;
  protected $paymentGatewayService;
  protected $vendDataService;
  protected $vendDispenseService;
  protected $vendTransactionService;

  public function __construct(

    MqttService $mqttService,
    PaymentGatewayService $paymentGatewayService,
    VendDataService $vendDataService,
    VendDispenseService $vendDispenseService,
    VendTransactionService $vendTransactionService
  ) {
    $this->mqttService = $mqttService;
    $this->paymentGatewayService = $paymentGatewayService;
    $this->vendDataService = $vendDataService;
    $this->vendDispenseService = $vendDispenseService;
  }

  public function createPaymentGatewayLog(Request $request, $company)
  {
    if (!$company) {
      throw new \Exception('Payment gateway not parsed in url');
    }

    $input = $request->all();
    $method = null;
    $status = null;
    $orderId = null;
    $refId = null;
    $qrRefID = null;

    switch ($company) {
      case 'midtrans':
        if (isset($input['transaction_status'])) {
          switch ($input['transaction_status']) {
            case 'pending':
              $status = PaymentGatewayLog::STATUS_PENDING;
              break;
            case 'capture':
            case 'settlement':
              $status = PaymentGatewayLog::STATUS_APPROVE;
              break;
            case 'cancel':
            case 'deny':
            case 'expire':
              $status = PaymentGatewayLog::STATUS_DECLINE;
              break;
            case 'refund':
            case 'partial_refund':
              $status = PaymentGatewayLog::STATUS_REFUND;
              break;
          }
        }
        $orderId = $input['order_id'];
        $refId = $input['transaction_id'];
        $qrRefID = null;
        break;

      case 'omise':
        switch ($input['data']['object']) {
          case 'charge':
          case 'source':
            if (isset($input['data']['status'])) {
              switch ($input['data']['status']) {
                case 'pending':
                  $status = PaymentGatewayLog::STATUS_PENDING;
                  break;
                case 'successful':
                  $status = PaymentGatewayLog::STATUS_APPROVE;
                  break;
                case 'failed':
                case 'expired':
                  $status = PaymentGatewayLog::STATUS_DECLINE;
                  break;
              }
            }
            break;
          case 'refund':
            $status = PaymentGatewayLog::STATUS_REFUND;
            break;
          default:
            throw new \Exception('Payment gateway is not found');
        }
        $orderId = $input['data']['metadata']['order_id'];
        $refId = $input['data']['id'];
        $qrRefID = isset($input['data']['source']) && isset($input['data']['source']['provider_references']) && isset($input['data']['source']['provider_references']['reference_number_1']) ? $input['data']['source']['provider_references']['reference_number_1'] : null;
        break;
      case 'fiuu':
        switch ($input['status'] ?? null) {
          case '00':
          case '0':
            $status = PaymentGatewayLog::STATUS_APPROVE;
            break;
          case '22':
            $status = PaymentGatewayLog::STATUS_PENDING;
            break;
          case '11':
          case '1':
          default:
            $status = PaymentGatewayLog::STATUS_DECLINE;
            break;
        }
        $orderId = $input['orderid'] ?? null;
        $refId = $input['tranID'] ?? null;
        $qrRefID = null;
        break;
    }

    $paymentGatewayLogSearchStatus = PaymentGatewayLog::STATUS_PENDING;
    switch ($status) {
      case PaymentGatewayLog::STATUS_PENDING:
      case PaymentGatewayLog::STATUS_APPROVE:
      case PaymentGatewayLog::STATUS_DECLINE:
        $paymentGatewayLogSearchStatus = PaymentGatewayLog::STATUS_PENDING;
        break;
      case PaymentGatewayLog::STATUS_REFUND:
        $paymentGatewayLogSearchStatus = PaymentGatewayLog::STATUS_APPROVE;
    }
    $paymentGatewayLog = PaymentGatewayLog::where('order_id', $orderId)->where('status', $paymentGatewayLogSearchStatus)->first();

    if (!$paymentGatewayLog) {
      return;
      // throw new \Exception('This payment is not trigger before');
    }

    switch ($company) {
      case 'midtrans':
        $method = isset($input['acquirer']) ? $input['acquirer'] : null;
        break;
      case 'omise':
        $method = isset($input['data']['source']['type']) ? $input['data']['source']['type'] : null;
        break;
      case 'fiuu':
        $method = $input['channel'] ?? null;
        break;
    }

    if ($company === 'fiuu' && $paymentGatewayLog) {
      $operatorPaymentGateway = $paymentGatewayLog->operatorPaymentGateway;
      if ($operatorPaymentGateway) {
        $fiuuGateway = new Fiuu(
          $operatorPaymentGateway->key1,
          $operatorPaymentGateway->key2,
          $operatorPaymentGateway->key3,
          $operatorPaymentGateway->type === OperatorPaymentGateway::TYPE_SANDBOX
        );

        if (!$fiuuGateway->verifyResponse($input)) {
          Log::warning('Fiuu callback signature mismatch', ['payload' => $input]);
          return;
        }
      }
    }

    $updatedPaymentGatewayLog = PaymentGatewayLog::updateOrCreate([
      'order_id' => $orderId,
    ], [
      // 'response' => $input,
      'method' => $paymentGatewayLog->method ? $paymentGatewayLog->method : $method,
      'qr_ref_id' => $qrRefID,
      'ref_id' => $refId,
      'status' => $status,
      'response' => $input,
    ]);

    if ($updatedPaymentGatewayLog and $status === PaymentGatewayLog::STATUS_APPROVE) {

      if ($paymentGatewayLog->created_at->diffInSeconds(Carbon::now()) > 210) {
        switch ($company) {
          case 'midtrans':
            break;
          case 'omise':
            RefundOmiseJob::dispatch($paymentGatewayLog->order_id);
            break;
        }
        return;
      }

      $updatedPaymentGatewayLog->update([
        'approved_at' => Carbon::now(),
      ]);

      // Schedule the "no-found-in-txn" daily-stats check. 5 minutes from now
      // we re-read this PG log; if the matching vend_transactions row has
      // still not landed (the visible "Found in Transactions?" flag on the
      // PaymentGatewayTransaction page is still red), we increment
      // vend_daily_stats.metric='nofound_txn' for this machine on the paid-at
      // date. VendTransactionService::createTransaction pairs this with a
      // DecrementVendDailyStat when the transaction eventually lands, so the
      // counter self-heals as the anomaly is resolved.
      LogNofoundTxnIfStillMissing::dispatch($updatedPaymentGatewayLog->id)
        ->delay(now()->addMinutes(5))
        ->onQueue('low');

      $this->processPayment($updatedPaymentGatewayLog);
    }
  }

  public function getPaymentMerchantsApi($countryCode = 'SG', $paymentGatewayName = 'omise')
  {
    $cacheKey = "payment_merchants:{$countryCode}:{$paymentGatewayName}";

    $dataArr = Cache::remember($cacheKey, 600, function () use ($countryCode, $paymentGatewayName) {
      $paymentMethods = PaymentMethod::query()
        ->with([
            'paymentGateway',
            'paymentMerchant',
          ])
        ->whereHas('paymentGateway', function ($query) use ($countryCode, $paymentGatewayName) {
          $query
            ->where('name', $paymentGatewayName)
            ->whereHas('country', function ($query) use ($countryCode) {
              $query->where('code', $countryCode);
            });
        })
        ->where('is_active', true)
        ->orderByRaw('ISNULL(sequence), sequence ASC')
        ->get();

      if ($paymentMethods->isEmpty()) {
        return [];
      }

      return $paymentMethods->map(function ($paymentMethod) {
        return [
          'name' => $paymentMethod->paymentMerchant->name,
          'image_url' => $paymentMethod->paymentMerchant->image_url,
          'slug' => $paymentMethod->type_name,
          'sequence' => $paymentMethod->sequence,
          'timeout_seconds' => 150,
          'is_default' => $paymentMethod->paymentGateway->default_payment_method_id === $paymentMethod->id,
        ];
      })->toArray();
    });

    return $dataArr;
  }

  private function processPayment(PaymentGatewayLog $paymentGatewayLog)
  {
    // $vend = Vend::where('code', ltrim(substr($paymentGatewayLog->order_id, -5), '0'))->first();
    if ($paymentGatewayLog->status === PaymentGatewayLog::STATUS_APPROVE and $paymentGatewayLog->paymentGateway()->exists()) {

      $paymentMethod = null;
      switch ($paymentGatewayLog->paymentGateway->name) {
        case 'midtrans':
          $paymentMethod = array_search($paymentGatewayLog->method, Midtrans::PAYMENT_METHOD_MAPPING);
          break;
        case 'omise':
          $paymentMethod = array_search($paymentGatewayLog->method, Omise::PAYMENT_METHOD_MAPPING);
          break;
        case 'fiuu':
          $paymentMethod = array_search($paymentGatewayLog->method, Fiuu::PAYMENT_METHOD_MAPPING);
          break;
      }

      if ($paymentGatewayLog->vend_channels_json) {
        $result = $this->vendDispenseService->getMultipleParam([
          'orderId' => $paymentGatewayLog->order_id,
          'amount' => $paymentGatewayLog->request['PRICE'],
          'vendCode' => $paymentGatewayLog->vend_code,
          'channels' => $paymentGatewayLog->vend_channels_json,
          'paymentMethod' => $paymentMethod,
          'txn_src' => $paymentGatewayLog->txn_src,
        ]);
      } else {
        $result = $this->vendDispenseService->getSingleParam([
          'orderId' => $paymentGatewayLog->order_id,
          'amount' => $paymentGatewayLog->request['PRICE'],
          'vendCode' => $paymentGatewayLog->vend_code,
          'productCode' => $paymentGatewayLog->vendChannel && $paymentGatewayLog->vendChannel->product()->exists() ? $paymentGatewayLog->vendChannel->product->code : null,
          'productID' => $paymentGatewayLog->vendChannel && $paymentGatewayLog->vendChannel->product()->exists() ? $paymentGatewayLog->vendChannel->product->id : null,
          'productName' => $paymentGatewayLog->vendChannel && $paymentGatewayLog->vendChannel->product()->exists() ? $paymentGatewayLog->vendChannel->product->name : null,
          'channelCode' => $paymentGatewayLog->vendChannel ? $paymentGatewayLog->vend_channel_code : null,
          'paymentMethod' => $paymentMethod,
          'txn_src' => $paymentGatewayLog->txn_src,
        ]);
      }

      $dataArr = [
        'fid' => $paymentGatewayLog->id,
        'result' => $result,
        'key' => $paymentGatewayLog->vend && $paymentGatewayLog->vend->private_key ? $paymentGatewayLog->vend->private_key : '123456789110138A',
      ];
      // $fid = $paymentGatewayLog->id;
      // $content = base64_encode(json_encode($result));
      // $contentLength = strlen($content);
      // $key = $paymentGatewayLog->vend && $paymentGatewayLog->vend->private_key ? $paymentGatewayLog->vend->private_key : '123456789110138A';
      // $md5 = md5($fid.','.$contentLength.','.$content.$key);

      // if($paymentGatewayLog->vend_code == '2007' or $paymentGatewayLog->vend_code == '2003' or $paymentGatewayLog->vend_code == '2009') {
      // $this->vendDispenseService->dispense($paymentGatewayLog->id, 'CM'.$paymentGatewayLog->vend_code, $fid.','.$contentLength.','.$content.','.$md5);
      $this->vendDispenseService->dispense($paymentGatewayLog->id, 'CM' . $paymentGatewayLog->vend_code, $dataArr);
      // }else {
      //   PublishMqtt::dispatch('CM'.$paymentGatewayLog->vend_code, $fid.','.$contentLength.','.$content.','.$md5)->onQueue('high');
      // }
    } else {
      PublishMqtt::dispatch('CM' . $paymentGatewayLog->vend_code, 'Error: QR code expired or payment gateway invalid')->onQueue('high');
      // $this->mqttService->publish('CM'.$paymentGatewayLog->vend_code, 'Error: QR code expired or payment gateway invalid');
    }
  }
}
