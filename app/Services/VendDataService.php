<?php

namespace App\Services;
use App\Models\Vend;
use App\Models\VendData;
use App\Jobs\Vend\CreateVendTransaction;
use App\Jobs\Vend\GetPaymentGatewayQR;
use App\Jobs\Vend\SyncVendChannels;
use App\Jobs\Vend\SyncVendParameter;
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
                    if(strpos($data, ' ')) {
                        $data = str_replace(' ', '+', $data);
                    }
                    if(substr($data, -1) == '!') {
                        $data = base64_decode(substr_replace($data,"=",-1));
                    }else {
                        $data = base64_decode($data);
                    }
                    $processedDataArr['content'] = $data;
                    break;
                default:
            }
        }

        if(str_starts_with($processedDataArr['content'], "{\"")) {
          $processedDataArr['data'] = json_decode($processedDataArr['content'], true);
        }else {
          $processedDataArr['data']['Vid'] = json_decode($processedDataArr['code'], true);
          $processedDataArr['data']['Type'] = 'CHANNEL';
          $processedDataArr['data']['channels'] = [];
          $byteData = unpack('C*', $processedDataArr['content']);

          if(!empty($byteData) && $byteData[1] == 83) {
            $byteSize = (sizeof($byteData) - 5)/ 11;
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

    if(isset($originalInput['m']) or isset($originalInput['Vid'])) {
      $vend = Vend::firstOrCreate([
          'code' => isset($originalInput['m']) ? $originalInput['m'] : $originalInput['Vid'],
      ]);
      switch($processedInput['Type']) {
        case 'CHANNEL':
          SyncVendChannels::dispatch($processedInput, $vend)->onQueue('default');
          break;
        case 'P':
          UpdateVendLastUpdated::dispatch($vend)->onQueue('default');
          $saveVendData = false;
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
          CreateVendTransaction::dispatch($processedInput, $vend)->onQueue('default');
          break;
        case 'VENDER':
          SyncVendParameter::dispatch($processedInput, $vend)->onQueue('default');
          break;
      }
    }

    if($saveVendData) {
      VendData::create([
        'connection' => $connectionType,
        'ip_address' => $ipAddress,
        'value' => $originalInput,
        'processed' => $processedInput,
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
      'pay_type' => 101,
      'price' => isset($params['amount']) ? $params['amount'] : 0,
      'score' => 1,
      'receivetime' => Carbon::now()->timestamp,
      'action' => 'TRADE',
      'mid' => isset($params['vendCode']) ? $params['vendCode'] : null,
      'shipment_info' => [
        'port_type' => 0,
        'goods_id' => isset($params['productCode']) ? $params['productCode'] : null,
        'goods_name' => isset($params['productName']) ? $params['productName'] : null,
        'goodroadid' => isset($params['channelCode']) ? $params['channelCode'] : null,
        'num' => 1,
        'uselift' => 0,
        'usedropchk' => 1,
      ],
    ];

    return $transactionParams;
  }
}