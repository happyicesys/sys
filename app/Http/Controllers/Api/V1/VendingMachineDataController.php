<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\VendingMachine;
use App\Models\VendingMachineChannel;
use App\Models\VendingMachineChannelError;
use App\Models\VendingMachineChannelErrorLog;
use App\Models\VendingMachineData;
use App\Models\VendingMachineTemp;
use App\Models\VendingMachineTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VendingMachineDataController extends Controller
{
    public function create(Request $request)
    {
        if($input = $request->all()) {
            $vendingMachineData = VendingMachineData::create([
                'value' => $input,
            ]);

            if($request->Vid) {
                $vendingMachine = VendingMachine::firstOrCreate([
                    'code' => $request->Vid,
                ]);

                if($vendingMachine) {
                    if($coinAmount = $request->CoinCnt) {
                        $vendingMachine->coin_amount = $coinAmount;
                    }

                    if($firmwareVer = $request->Ver) {
                        $vendingMachine->firmware_ver = $firmwareVer;
                    }

                    if($isDoorOpen = $request->door) {
                        $vendingMachine->firmware_ver = $firmwareVer;
                    }

                    if($isSensorNormal = $request->Sensor) {
                        $vendingMachine->is_sensor_normal = $isSensorNormal;
                    }

                    $vendingMachine->save();

                    if($type = $request->Type) {
                        switch($type) {
                            case 'VENDER':
                                if($temp = $request->TEMP) {
                                    $this->createVendingMachineTemp($vendingMachine, $temp);
                                }
                                break;
                            case 'TRADE':
                                $this->createVendingMachineTransaction($vendingMachine, $request);
                                break;
                            case 'CHANNEL':
                                $this->syncVendingMachineChannels($vendingMachine, $request);
                                break;
                        }
                    }
                }
            }
        }
    }

    private function createVendingMachineTemp(VendingMachine $vendingMachine, $temp)
    {
        // more than 3 minutes only update same machine temp
        if(!$vendingMachine->temp_updated_at or $vendingMachine->temp_updated_at->addMinutes(2)->isPast()) {
            if($temp == VendingMachineTemp::TEMPERATURE_ERROR) {
                $vendingMachine->is_temp_error = true;
            }else {
                $vendingMachine->vendingMachineTemps()->create([
                    'value' => $temp,
                ]);

                $vendingMachine->temp = $temp;
                $vendingMachine->temp_updated_at = Carbon::now();
                $vendingMachine->is_temp_error = false;
            }

            $vendingMachine->save();
        }
    }


    private function createVendingMachineTransaction(VendingMachine $vendingMachine, $request)
    {
        if($payType = $request->PAY_TYPE) {
            $paymentMethod = PaymentMethod::where('code', $payType)->first();
        }

        if($sID = $request->SId) {
            $vendingMachineChannel = VendingMachineChannel::where('code', $sID)->where('vending_machine_id', $vendingMachine->id)->first();

            if(!$vendingMachineChannel) {
                $vendingMachineChannel = VendingMachineChannel::create([
                    'code' => $sID,
                    'vending_machine_id' => $vendingMachine->id,
                ]);
            }
        }

        $vendingMachineChannelError = VendingMachineChannelError::where('code', $request->sErr)->first();

        VendingMachineTransaction::create([
            'order_id' => $request->ORDRID,
            'transaction_datetime' => $request->TIME,
            'amount' => $request->Price,
            'payment_method_id' => isset($paymentMethod) ? $paymentMethod->id : 0,
            'vending_machine_id' => $vendingMachine->id,
            'vending_machine_channel_id' => isset($vendingMachineChannel) ? $vendingMachineChannel->id : 0,
            'vending_machine_channel_error_id' => isset($vendingMachineChannelError) ? $vendingMachineChannelError->id : null
        ]);

        if($vendingMachineChannelError) {
            $this->syncVendingMachineChannelErrorLog($vendingMachine, $request->SId, $request->sErr);
        }
    }

    private function syncVendingMachineChannels(VendingMachine $vendingMachine, $request)
    {
        if($channels = $request->channels) {
            foreach($channels as $channel) {
                if($channel['capacity'] > 0) {
                    VendingMachineChannel::updateOrCreate([
                        'vending_machine_id' => $vendingMachine->id,
                        'code' => $channel['channel_code'],
                    ], [
                        'qty' => $channel['qty'],
                        'capacity' => $channel['capacity'],
                        'amount' => $channel['amount'],
                    ]);
                    $this->syncVendingMachineChannelErrorLog($vendingMachine, $channel['channel_code'], $channel['error_code']);
                }else if($channel['capacity'] == 0) {
                    $zeroCapacityChannel = VendingMachineChannel::where('vending_machine_id', $vendingMachine->id)
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

    private function syncVendingMachineChannelErrorLog(VendingMachine $vendingMachine, $vendingMachineChannelCode, $vendingMachineChannelErrorCode)
    {
        $vendingMachineChannelError = VendingMachineChannelError::where('code', $vendingMachineChannelErrorCode)->first();

        if($vendingMachineChannelError) {
            if($vendingMachineChannelError->code > 0) {
                $vendingMachineChannel = VendingMachineChannel::firstOrCreate([
                    'vending_machine_id' => $vendingMachine->id,
                    'code' => $vendingMachineChannelCode,
                ]);

                VendingMachineChannelErrorLog::create([
                    'vending_machine_channel_id' => $vendingMachineChannel->id,
                    'vending_machine_channel_error_id' => $vendingMachineChannelError->id
                ]);
            }else {
                $recoveredChannel = VendingMachineChannel::where('vending_machine_id', $vendingMachine->id)->where('code', $vendingMachineChannelCode)->first();
                if($recoveredChannel) {
                    $recoveredVendingMachineChannelErrorLogs = VendingMachineChannelErrorLog::where('vending_machine_channel_id', $recoveredChannel->id)->get();
                    if($recoveredVendingMachineChannelErrorLogs) {
                        foreach($recoveredVendingMachineChannelErrorLogs as $recoveredVendingMachineChannelErrorLog) {
                            $recoveredVendingMachineChannelErrorLog->is_error_cleared = true;
                            $recoveredVendingMachineChannelErrorLog->save();
                        }
                    }
                }

            }
        }
    }
}
