<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendChannelErrorLog;
use App\Models\VendData;
use App\Models\VendTemp;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VendDataController extends Controller
{
    public function create(Request $request)
    {
        if($input = $request->all()) {
            $vendData = VendData::create([
                'value' => $input,
            ]);

            if($request->Vid) {
                $vend = Vend::firstOrCreate([
                    'code' => $request->Vid,
                ]);

                if($vend) {
                    if($coinAmount = $request->CoinCnt) {
                        $vend->coin_amount = $coinAmount;
                    }

                    if($firmwareVer = $request->Ver) {
                        $vend->firmware_ver = $firmwareVer;
                    }

                    if($isDoorOpen = $request->door) {
                        $vend->firmware_ver = $firmwareVer;
                    }

                    if($isSensorNormal = $request->Sensor) {
                        $vend->is_sensor_normal = $isSensorNormal;
                    }

                    $vend->save();

                    if($type = $request->Type) {
                        switch($type) {
                            case 'VENDER':
                                if($temp = $request->TEMP) {
                                    $this->createVendTemp($vend, $temp);
                                }
                                break;
                            case 'TRADE':
                                $this->createVendTransaction($vend, $request);
                                break;
                            case 'CHANNEL':
                                $this->syncVendChannels($vend, $request);
                                break;
                        }
                    }
                }
            }
        }
    }

    private function createVendTemp(Vend $vend, $temp)
    {
        // more than 3 minutes only update same machine temp
        if(!$vend->temp_updated_at or $vend->temp_updated_at->addMinutes(2)->isPast()) {
            if($temp == VendTemp::TEMPERATURE_ERROR) {
                $vend->is_temp_error = true;
            }else {
                $vend->vendTemps()->create([
                    'value' => $temp,
                ]);

                $vend->temp = $temp;
                $vend->temp_updated_at = Carbon::now();
                $vend->is_temp_error = false;
            }

            $vend->save();
        }
    }


    private function createVendTransaction(Vend $vend, $request)
    {
        // if($payType = $request->PAY_TYPE) {
        $paymentMethod = PaymentMethod::where('code', $request->PAY_TYPE)->first();
        // }

        if($sID = $request->SId) {
            $vendChannel = VendChannel::where('code', $sID)->where('vend_id', $vend->id)->first();

            if(!$vendChannel) {
                $vendChannel = VendChannel::create([
                    'code' => $sID,
                    'vend_id' => $vend->id,
                ]);
            }
        }

        $vendChannelError = VendChannelError::where('code', $request->sErr)->first();

        VendTransaction::create([
            'order_id' => $request->ORDRID,
            'transaction_datetime' => Carbon::createFromFormat('Y-m-d H:i:s', $request->TIME),
            'amount' => $request->Price,
            'payment_method_id' => $paymentMethod ? $paymentMethod->id : null,
            'vend_id' => $vend->id,
            'vend_channel_id' => isset($vendChannel) ? $vendChannel->id : 0,
            'vend_channel_error_id' => isset($vendChannelError) ? $vendChannelError->id : null
        ]);

        if($vendChannelError) {
            $this->syncVendChannelErrorLog($vend, $request->SId, $request->sErr);
        }
    }

    private function syncVendChannels(Vend $vend, $request)
    {
        if($channels = $request->channels) {
            foreach($channels as $channel) {
                if($channel['capacity'] > 0) {
                    VendChannel::updateOrCreate([
                        'vend_id' => $vend->id,
                        'code' => $channel['channel_code'],
                    ], [
                        'qty' => $channel['qty'],
                        'capacity' => $channel['capacity'],
                        'amount' => $channel['amount'],
                    ]);
                    $this->syncVendChannelErrorLog($vend, $channel['channel_code'], $channel['error_code']);
                }else if($channel['capacity'] == 0) {
                    $zeroCapacityChannel = VendChannel::where('vend_id', $vend->id)
                                                            ->where('code', $channel['channel_code'])
                                                            ->first();
                    if($zeroCapacityChannel) {
                        $zeroCapacityChannel->is_active = false;
                        $zeroCapacityChannel->save();
                    }
                }
            }
        }
    }

    private function syncVendChannelErrorLog(Vend $vend, $vendChannelCode, $vendChannelErrorCode)
    {
        $vendChannelError = VendChannelError::where('code', $vendChannelErrorCode)->first();

        if($vendChannelError) {
            if($vendChannelError->code > 0) {
                $vendChannel = VendChannel::firstOrCreate([
                    'vend_id' => $vend->id,
                    'code' => $vendChannelCode,
                ]);

                $lastVendChannelErrorLog = $vendChannel->vendChannelErrorLogs()->latest()->first();

                if(!$lastVendChannelErrorLog or ($lastVendChannelErrorLog->vendChannelError->code != $vendChannelErrorCode) or $lastVendChannelErrorLog->is_error_cleared == true) {
                    VendChannelErrorLog::create([
                        'vend_channel_id' => $vendChannel->id,
                        'vend_channel_error_id' => $vendChannelError->id
                    ]);
                }

            }else {
                $recoveredChannel = VendChannel::where('vend_id', $vend->id)->where('code', $vendChannelCode)->first();
                if($recoveredChannel) {
                    $recoveredVendChannelErrorLogs = VendChannelErrorLog::where('vend_channel_id', $recoveredChannel->id)->get();
                    if($recoveredVendChannelErrorLogs) {
                        foreach($recoveredVendChannelErrorLogs as $recoveredVendChannelErrorLog) {
                            $recoveredVendChannelErrorLog->is_error_cleared = true;
                            $recoveredVendChannelErrorLog->save();
                        }
                    }
                }

            }
        }
    }
}
