<?php

namespace App\Services;
use App\Models\Vend;
use App\Models\VendData;
use App\Jobs\SyncAcbVmcPa;
use App\Jobs\SyncAcbStatus;
use App\Jobs\SyncIsMqttVend;
use App\Jobs\Vend\CreateVendTransaction;
use App\Jobs\Vend\GetPaymentGatewayQR;
use App\Jobs\Vend\GetPurchaseConfirm;
use App\Jobs\Vend\SyncVendChannels;
use App\Jobs\Vend\SyncVendParameter;
use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Jobs\Vend\UpdateApkVersion;
use App\Jobs\Vend\UpdateVendLastUpdated;
use Carbon\Carbon;
use PhpMqtt\Client\Facades\MQTT;

class VendDataService
{
  public function standardizedVendData($input, $connectionType)
  {
    $input = collect($input);

    if(strpos($input, '&') !== false) {
      $input = $input->first();
      foreach(explode('&', $input) as $processInput) {
        list($a, $b) = explode('=', $processInput);
        $finalInput[$a] = $b;
      }
      $finalInput = collect($finalInput);
    }else {
      $finalInput = $input;
    }
    return $finalInput;
  }

  public function decodeVendData($input) {
    $data = [];
    $processedDataArr = [];

    if(isset($input['f']) and isset($input['g']) and isset($input['m']) and isset($input['p']) and isset($input['t'])) {
        foreach($input as $dataIndex => $data) {
            switch($dataIndex) {
                case 'f':
                    break;
                case 't':
                    break;
                case 'm':
                    $processedDataArr['code'] = $data;
                    break;
                case 'g':
                    break;
                case 'p':
                    if(isset($data)) {
                      if(strpos($data, ' ')) {
                          $data = str_replace(' ', '+', $data);
                      }
                      if(substr($data, -1) == '!') {
                          $data = base64_decode(substr_replace($data,"=",-1));
                      }else {
                          $data = base64_decode($data);
                      }
                      $processedDataArr['content'] = $data;
                    }
                    break;
                default:
            }
        }

        if(str_starts_with($processedDataArr['content'], "{\"") or empty($input['p'])) {
          $processedDataArr['data'] = json_decode($processedDataArr['content'], true);
        }else {
          $processedDataArr['data']['Vid'] = json_decode($processedDataArr['code'], true);
          $processedDataArr['data']['Type'] = 'CHANNEL';
          $processedDataArr['data']['channels'] = [];
          $byteData = unpack('C*', $processedDataArr['content']);

          if(!empty($byteData) && $byteData[1] == 83) {
            $processedDataArr['data']['label'] = 'S';
            $byteSize = (sizeof($byteData) - 5)/ 11;
            if($byteSize == 60) {
              // INT16U id;
              // INT8U Col_FaultCode;
              // INT8U Col_Capacity;
              // INT8U Col_GoodsCount;
              // INT32U Col_Price;
              // INT16U Col_ProductId;
              $i = 2;
              $i += 4;
              for($j = 0; $j < $byteSize; $j++) {
                $channelArr = [];
                $channelCode = $byteData[$i++];
                $channelCode += $byteData[$i++]*0x100;
                $channelArr['channel_code'] = $channelCode;

                $channelArr['error_code'] = $byteData[$i++];
                $channelArr['capacity'] = $byteData[$i++];
                $channelArr['qty'] = $byteData[$i++];

                $amount = $byteData[$i++];
                $amount += $byteData[$i++]*0x100;
                $amount += $byteData[$i++]*0x10000;
                $amount += $byteData[$i++]*0x1000000;
                $channelArr['amount'] = $amount;
                $i += 2;
                if(is_array($channelArr)) {
                  array_push($processedDataArr['data']['channels'], $channelArr);
                }
              }
            }else {
              // INT16U id;
              // INT8U Col_FaultCode;
              // INT8U Col_Capacity;
              // INT8U Col_GoodsCount;
              // INT32U Col_Price;
              // INT16U Col_ProductId;
              // INT16U discount_grp;
              // INT32U Col_Price2;
              // INT16U lock_cnt;
              if(!empty($byteData) && $byteData[1] == 65) {
                $processedDataArr['data']['label'] = 'A';
              }
              if(!empty($byteData) && $byteData[1] == 66) {
                $processedDataArr['data']['label'] = 'B';
              }
              $byteSize = (sizeof($byteData) - 5)/ 19;
              $i = 2;
              $i += 4;
              for($j = 0; $j < $byteSize; $j++) {
                $channelArr = [];
                $channelCode = $byteData[$i++];
                $channelCode += $byteData[$i++]*0x100;
                $channelArr['channel_code'] = $channelCode;
                $channelArr['error_code'] = $byteData[$i++];
                $channelArr['capacity'] = $byteData[$i++];
                $channelArr['qty'] = $byteData[$i++];
                $amount = $byteData[$i++];
                $amount += $byteData[$i++]*0x100;
                $amount += $byteData[$i++]*0x10000;
                $amount += $byteData[$i++]*0x1000000;
                $channelArr['amount'] = $amount;
                $productId = $byteData[$i++];
                $productId += $byteData[$i++]*0x100;
                $channelArr['product_id'] = $productId;
                $discountGroup = $byteData[$i++];
                $discountGroup += $byteData[$i++]*0x100;
                $channelArr['discount_group'] = $discountGroup;
                $amount2 = $byteData[$i++];
                $amount2 += $byteData[$i++]*0x100;
                $amount2 += $byteData[$i++]*0x10000;
                $amount2 += $byteData[$i++]*0x1000000;
                $channelArr['amount2'] = $amount2;
                $lockQty = $byteData[$i++];
                $lockQty += $byteData[$i++]*0x100;
                $channelArr['lock_qty'] = $lockQty;
                if(is_array($channelArr)) {
                  array_push($processedDataArr['data']['channels'], $channelArr);
                }
              }
            }
          }
        }
      $data = $processedDataArr['data'];
    }else {
      $data = $input;
    }
    return $data;
  }

  public function processVendData($originalInput, $processedInput, $ipAddress, $connectionType)
  {
    $response = isset($originalInput['f']) ? $originalInput['f'].',4,MQ==' : true;
    $saveVendData = true;
    $requiredMd5 = false;

    if(isset($originalInput['m'])) {

      $vend = Vend::with('latestVendBinding.customer')->where('code', $originalInput['m'])->first();

      if(!$vend) {
        return $response;
      }

      // $vend = Vend::with('latestVendBinding.customer')->firstOrCreate([
      //     'code' => isset($originalInput['m']) ? $originalInput['m'] : $originalInput['Vid'],
      // ]);
      if(!$vend->vend_transaction_totals_json) {
        SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('default');
      }
      if(isset($processedInput['Type'])) {
        switch($processedInput['Type']) {
          case 'ACBVMCPA':
            SyncAcbVmcPa::dispatch($processedInput, $vend)->onQueue('default');
            break;
          case 'ACBSTATUS':
            SyncAcbStatus::dispatch($processedInput, $vend)->onQueue('default');
            break;
          case 'CHANNEL':
            SyncVendChannels::dispatch($processedInput, $vend)->onQueue('default');
            break;
          case 'CONFIRM':
            if(isset($processedInput['orderid'])) {
              GetPurchaseConfirm::dispatch($processedInput['orderid'], $vend)->onQueue('high');
            }
            break;
          case 'P':
            UpdateVendLastUpdated::dispatch($vend)->onQueue('default');
            $saveVendData = false;
            break;
          case 'PWRON':
            UpdateApkVersion::dispatch($processedInput, $vend)->onQueue('default');
            SyncIsMqttVend::dispatch($vend)->onQueue('default');
            break;
          case 'REQQR':
            GetPaymentGatewayQR::dispatch($originalInput, $processedInput, $vend)->onQueue('high');
            break;
          case 'TIME':
            $operatorTimezone = 'Asia/Singapore';
            if($vend->operators()->exists()) {
              $operatorTimezone = $vend->operators()->first()->timezone;
            }
            $response = isset($originalInput['f']) ?
            $originalInput['f'].','.strlen(base64_encode('TIME'.Carbon::now()->setTimezone($operatorTimezone)->format('Y-m-d H:i:s'))).','.base64_encode('TIME'.Carbon::now()->setTimezone($operatorTimezone)->format('Y-m-d H:i:s')) :
            true;
            break;
          case 'TRADE':
            CreateVendTransaction::dispatch($processedInput, $vend, true)->onQueue('default');
            break;
          case 'VENDER':
            SyncVendParameter::dispatch($processedInput, $vend)->onQueue('default');
            break;
          default:
            VendData::create([
              'connection' => $connectionType,
              'ip_address' => $ipAddress,
              'processed' => $processedInput,
              'type' => isset($processedInput['Type']) ? $processedInput['Type'] : 'error',
              'value' => $originalInput,
              'vend_code' => isset($originalInput['m']) ? $originalInput['m'] : null,
            ]);
            throw new \Exception('Type is not set or please check the parameters');
        }
      }else {
        UpdateVendLastUpdated::dispatch($vend)->onQueue('default');
        $saveVendData = false;
      }
    }

    if($saveVendData) {
      VendData::create([
        'connection' => $connectionType,
        'ip_address' => $ipAddress,
        'processed' => $processedInput,
        'type' => isset($processedInput['Type']) ? $processedInput['Type'] : null,
        'value' => $originalInput,
        'vend_code' => isset($originalInput['m']) ? $originalInput['m'] : null,
      ]);
    }

    return $response;
  }

  public function getPurchaseRequest($params)
  {
    $transactionParams = [];
    $transactionParams = [
      'Type' => 'TRADE',
      'orderid' => isset($params['orderId']) ? $params['orderId'] : null,
      'get_type' => 1,
      'pay_type' => $params['paymentMethod'],
      'price' => isset($params['amount']) ? $params['amount'] : 0,
      'score' => 1,
      'receivetime' => Carbon::now()->timestamp,
      'action' => 'TRADE',
      'mid' => isset($params['vendCode']) ? (int)$params['vendCode'] : null,
      'shipment_info' => [
        [
        'port_type' => 0,
        'goods_id' => 0,
        'goods_name' => null,
        'goodroadid' => isset($params['channelCode']) ? (int)$params['channelCode'] : null,
        'num' => 1,
        'uselift' => 0,
        'usedropchk' => 1,
        ]
      ],
    ];
    // log for error checking
    // VendData::create([
    //   'connection' => 'mqtt',
    //   'vend_code' => isset($params['vendCode']) ? (int)$params['vendCode'] : null,
    //   'value' => $transactionParams,
    // ]);

    return $transactionParams;
  }
}