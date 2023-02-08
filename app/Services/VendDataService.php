<?php

namespace App\Services;
use App\Models\Vend;
use App\Models\VendData;
use Carbon\Carbon;

class VendDataService
{
  public function standardizedVendData($input)
  {
    $finalInput = [];
    if($connectionType === 'mqtt') {
      foreach(explode('&', $input) as $processInput) {
          list($a, $b) = explode('=', $processInput);
          $finalInput[$a] = $b;
      }
    }else {
      $finalInput = $input;
    }

    return $finalInput;
  }

  public function decodeVendData($input) {
    $data = [];
    $processedDataArr = [];
    if($input['f'] and $input['g'] and $input['f'] and $input['p'] and $input['t']) {
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
            // if($processedDataArr['content'] === "{\"Type\":\"P\"}") {
            //     if(isset($processedDataArr['code']) and $processedDataArr['code']) {
            //         $vend = Vend::where('code', $processedDataArr['code'])->first();
            //         if($vend) {
            //             $this->vendSaveLastUpdatedTime($vend);
            //         }
            //     }
            //     $processedDataArr['data'] = null;
            // }else {
              $processedDataArr['data'] = json_decode($processedDataArr['content'], true);
            // }
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
      $data = $vendData->value;
    }

    return $data;
  }

  public function processVendData($originalInput, $processedInput, $ipAddress, $connectionType)
  {
    $response = '';

    if(isset($processedInput['Vid'])) {
      $vend = Vend::firstOrCreate([
          'code' => $processedInput['Vid'],
      ]);

      switch($processedInput['Type']) {
        case 'CHANNEL':
          SyncVendChannels::dispatch($processedInput, $vend);
          $response = $input['f'].',4,MQ==';
          break;
        case 'P':
          $vend->last_updated_at = Carbon::now();
          $vend->save();
          $response = $input['f'].',4,MQ==';
          break;
        case 'REQQR':
          // queue, cut queue, mqtt publish
          // save payment gateway log
          // return fid,len,BASE64(QRCODEmidtransbarcodeUrl,selfGenerateOrderId)
          break;
        case 'TIME':
          // live
          // return fid,len,BASE64(TIMEyyyy-MM-dd HH:mm:ss)
          break;
        case 'TRADE':
          // queue
          // record vend transactions
          // return fid,len,BASE64(1)  fid,4,MQ==
          break;
        case 'VENDER':
          // queue (fan, temp, parameter)
          // return fid,len,BASE64(1)  fid,4,MQ==
          break;

      }
    }
  }
}