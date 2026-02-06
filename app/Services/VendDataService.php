<?php

namespace App\Services;
use App\Models\Customer;
use App\Models\ModemUnit;
use App\Models\Scopes\OperatorCustomerFilterScope;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Models\VendData;
use App\Models\VendJob;
use App\Jobs\PublishMqtt;
use App\Jobs\SendHttpDataToLogServer;
use App\Jobs\SyncAcbVmcPa;
use App\Jobs\SyncAcbStatus;
use App\Jobs\SyncDeliveryPlatformMenu;
use App\Jobs\SyncIsMqttVend;
use App\Jobs\SyncP;
use App\Jobs\UpdateHttpLastUpdated;
use App\Jobs\UpdateModemLastUpdated;
use App\Jobs\CreateVendData;
use App\Jobs\Vend\CreateVendStatistics;
use App\Jobs\Vend\CreateVendTransaction;
use App\Jobs\Vend\GetPaymentGatewayQR;
use App\Jobs\Vend\GetPurchaseConfirm;
use App\Jobs\Vend\SyncVendChannels;
use App\Jobs\Vend\SyncVendParameter;
use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Jobs\Vend\UpdateApkVersion;
use App\Jobs\Vend\UpdateMqttLastUpdated;
use App\Jobs\Vend\UpdateVendStatistics;
use App\Jobs\Vend\SyncFeatureApkSetting;
use App\Jobs\Vend\SyncJobApkSetting;
use Carbon\Carbon;
use PhpMqtt\Client\Facades\MQTT;
use Illuminate\Support\Facades\Cache;

class VendDataService
{
  public function standardizedVendData($input, $connectionType)
  {
    $input = collect($input);

    if (strpos($input, '&') !== false) {
      $input = $input->first();
      foreach (explode('&', $input) as $processInput) {
        list($a, $b) = explode('=', $processInput);
        $finalInput[$a] = $b;
      }
      $finalInput = collect($finalInput);
    } else {
      $finalInput = $input;
    }
    return $finalInput;
  }

  public function decodeVendData($input)
  {
    $data = [];
    $processedDataArr = [];

    if (isset($input['f']) and isset($input['g']) and isset($input['m']) and isset($input['p']) and isset($input['t'])) {
      foreach ($input as $dataIndex => $data) {
        switch ($dataIndex) {
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
            if (isset($data)) {
              if (strpos($data, ' ')) {
                $data = str_replace(' ', '+', $data);
              }
              if (substr($data, -1) == '!') {
                $data = base64_decode(substr_replace($data, "=", -1));
              } else {
                $data = base64_decode($data);
              }
              $processedDataArr['content'] = $data;
            }
            break;
          default:
        }
      }

      if (str_starts_with($processedDataArr['content'], "{\"") or empty($input['p'])) {
        $jsonData = json_decode($processedDataArr['content'], true);

        $processedDataArr['data'] = $jsonData;

      } else {
        $processedDataArr['data']['Vid'] = json_decode($processedDataArr['code'], true);
        $processedDataArr['data']['Type'] = 'CHANNEL';
        $processedDataArr['data']['channels'] = [];
        $byteData = unpack('C*', $processedDataArr['content']);

        if (!empty($byteData)) {
          switch ($byteData[1]) {
            case 65:
              $processedDataArr['data']['label'] = 'A';
              break;
            case 66:
              $processedDataArr['data']['label'] = 'B';
              break;
            case 67:
              $processedDataArr['data']['label'] = 'C';
              break;
            case 83:
              $processedDataArr['data']['label'] = 'S';
              break;
            default:
              $processedDataArr['data']['label'] = 'error';
          }
        }

        // if(!empty($byteData) && $byteData[1] == 83) {
        if (!empty($byteData)) {
          $byteSize = (sizeof($byteData) - 5) / 11;
          if ($byteSize == 60) {
            // INT16U id;
            // INT8U Col_FaultCode;
            // INT8U Col_Capacity;
            // INT8U Col_GoodsCount;
            // INT32U Col_Price;
            // INT16U Col_ProductId;
            $i = 2;
            $i += 4;
            for ($j = 0; $j < $byteSize; $j++) {
              $channelArr = [];
              $channelCode = $byteData[$i++];
              $channelCode += $byteData[$i++] * 0x100;
              $channelArr['channel_code'] = $channelCode;

              $channelArr['error_code'] = $byteData[$i++];
              $channelArr['capacity'] = $byteData[$i++];
              $channelArr['qty'] = $byteData[$i++];

              $amount = $byteData[$i++];
              $amount += $byteData[$i++] * 0x100;
              $amount += $byteData[$i++] * 0x10000;
              $amount += $byteData[$i++] * 0x1000000;
              $channelArr['amount'] = $amount;
              $i += 2;
              if (is_array($channelArr)) {
                array_push($processedDataArr['data']['channels'], $channelArr);
              }
            }
          } else {
            // INT16U id;
            // INT8U Col_FaultCode;
            // INT8U Col_Capacity;
            // INT8U Col_GoodsCount;
            // INT32U Col_Price;
            // INT16U Col_ProductId;
            // INT16U discount_grp;
            // INT32U Col_Price2;
            // INT16U lock_cnt;
            $byteSize = (sizeof($byteData) - 5) / 19;
            $i = 2;
            if ($processedDataArr['data']['label'] === 'S') {
              $i += 4;
            } else {
              $i += 2;
            }
            for ($j = 0; $j < $byteSize; $j++) {
              $channelArr = [];
              $channelCode = $byteData[$i++];
              $channelCode += $byteData[$i++] * 0x100;
              $channelArr['channel_code'] = $channelCode;
              $channelArr['error_code'] = $byteData[$i++];
              $channelArr['capacity'] = $byteData[$i++];
              $channelArr['qty'] = $byteData[$i++];
              $amount = $byteData[$i++];
              $amount += $byteData[$i++] * 0x100;
              $amount += $byteData[$i++] * 0x10000;
              $amount += $byteData[$i++] * 0x1000000;
              $channelArr['amount'] = $amount;
              $productId = $byteData[$i++];
              $productId += $byteData[$i++] * 0x100;
              $channelArr['product_id'] = $productId;
              $discountGroup = $byteData[$i++];
              $discountGroup += $byteData[$i++] * 0x100;
              $channelArr['discount_group'] = $discountGroup;
              $amount2 = $byteData[$i++];
              $amount2 += $byteData[$i++] * 0x100;
              $amount2 += $byteData[$i++] * 0x10000;
              $amount2 += $byteData[$i++] * 0x1000000;
              $channelArr['amount2'] = $amount2;
              $lockQty = $byteData[$i++];
              $lockQty += $byteData[$i++] * 0x100;
              $channelArr['lock_qty'] = $lockQty;
              if (is_array($channelArr)) {
                array_push($processedDataArr['data']['channels'], $channelArr);
              }
            }
          }
        }
      }
      $data = $processedDataArr['data'];
    } else {
      $data = $input;
    }
    return $data;
  }

  public function processVendData($originalInput, $processedInput, $ipAddress, $connectionType)
  {
    $response = isset($originalInput['f']) ? $originalInput['f'] . ',4,MQ==' : true;
    $saveVendData = true;
    $requiredMd5 = false;

    if (isset($originalInput['m'])) {
      // Optimize query by removing global scope (runs in unauthenticated context)
      // and lazy loading customer (only needed in specific condition below)
      $vend = Vend::withoutGlobalScope(OperatorVendFilterScope::class)
        ->where('code', $originalInput['m'])
        ->first();

      if (!$vend) {
        $modem = ModemUnit::whereRaw("TRIM(LEADING '0' FROM RIGHT(imei, 6)) = ?", [$originalInput['m']])
          ->first();

        if (!$modem) {
          return $response;
        }
        UpdateModemLastUpdated::dispatch($modem)->onQueue('default');
        PublishMqtt::dispatch('CX' . ltrim(substr($modem->imei, -6), "0"), $response, 0)->onQueue('default');

        return $response;
      }

      // Lazy load customer only when needed (without global scope for performance)
      if ($vend->customer_id) {
        $customer = Customer::withoutGlobalScope(OperatorCustomerFilterScope::class)
          ->find($vend->customer_id);
        if ($customer && !$customer->totals_json) {
          SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('default');
        }
      }

      if (isset($processedInput['Type'])) {
        switch ($processedInput['Type']) {
          case 'ACBVMCPA':
            SyncAcbVmcPa::dispatch($processedInput, $vend)->onQueue('default');
            break;
          case 'ACBSTATUS':
            SyncAcbStatus::dispatch($processedInput, $vend)->onQueue('default');
            break;
          case 'CHANNEL':
            SyncVendChannels::dispatch($processedInput, $vend)->onQueue('high');
            break;
          case 'CONFIRM':
            if (isset($processedInput['orderid'])) {
              GetPurchaseConfirm::dispatch($processedInput['orderid'], $vend)->onQueue('high');
            }
            break;
          case 'JOBAPKSETTING':
            SyncJobApkSetting::dispatch($processedInput, $vend)->onQueue('default');
            break;
          case 'FEATUREAPKSETTING':
            SyncFeatureApkSetting::dispatch($processedInput, $vend)->onQueue('default');
            break;
          case 'PWRON':
            UpdateApkVersion::dispatch($processedInput, $vend)->onQueue('default');
            break;
          case 'REFILL':
            break;
          case 'REQQR':
            $timezone = $vend->operator->timezone ?? config('app.timezone');

            // Hardcoded maintenance window
            $start = Carbon::create(2026, 1, 11, 0, 0, 0, $timezone);
            $end = Carbon::create(2026, 1, 11, 6, 0, 0, $timezone);

            $now = Carbon::now($timezone);

            if ($now->between($start, $end)) {
              break; // skip during maintenance
            }

            GetPaymentGatewayQR::dispatch($originalInput, $processedInput, $vend)
              ->onQueue('high');
            break;
          case 'STATIS1':
            UpdateVendStatistics::dispatch($processedInput, $vend)->onQueue('default');
            break;
          case 'TIME':
            $operatorTimezone = config('app.timezone');
            if ($vend->operator) {
              $operatorTimezone = $vend->operator->timezone;
            }
            $response = isset($originalInput['f']) ?
              $originalInput['f'] . ',' . strlen(base64_encode('TIME' . Carbon::now()->setTimezone($operatorTimezone)->format('Y-m-d H:i:s'))) . ',' . base64_encode('TIME' . Carbon::now()->setTimezone($operatorTimezone)->format('Y-m-d H:i:s')) :
              true;
            break;
          case 'TRADE':
            CreateVendTransaction::dispatch($processedInput, $vend, true)->onQueue('default');
            break;
          case 'VENDER':
            SyncVendParameter::dispatch($processedInput, $vend)->onQueue('default');
            break;
          case 'P':
            SyncP::dispatch($processedInput, $vend)->onQueue('default');
            $saveVendData = false;
            break;
          default:
            $saveVendData = true;
        }
      }

      if ($connectionType == 'http') {
        UpdateHttpLastUpdated::dispatch($vend->id)->onQueue('default');
      }

      if ($connectionType == 'mqtt') {
        UpdateMqttLastUpdated::dispatch($vend->id)->onQueue('default');

        if ($vend->apk_ver_json && $vend->apk_ver_json['apkver'] && $vend->apk_ver_json['apkver'] >= 129) {
          PublishMqtt::dispatch('CM' . $vend->code, $response, 0)->onQueue('default');
        }
      }

      // Trigger smart alert check for this specific vend (Debounced: Run max once every 3 mins)
      if (!Cache::has('detect_temp_trends_' . $vend->id)) {
        \App\Jobs\DetectTempTrends::dispatch($vend->id)->onQueue('low');
        Cache::put('detect_temp_trends_' . $vend->id, true, now()->addMinutes(3));
      }

    }

    if ($connectionType == 'mqtt') {
      $saveVendData = false;
    }

    if ($saveVendData) {
      if (config('app.env') == 'production' && config('app.log_server_url') && config('app.log_server_access_token')) {
        SendHttpDataToLogServer::dispatch($originalInput)->onQueue('default');
      }
      // CreateVendData::dispatch($originalInput, $processedInput, $ipAddress, $connectionType)->onQueue('default');
    }

    return response()->json($response);
  }
}