<?php

namespace App\Jobs;

use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendChannelErrorLog;
use App\Models\VendData;
use App\Models\VendTemp;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessVendData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $input;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($input)
    {
        $this->input = $input;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // $processedDataArr = [];
        // foreach($input as $dataIndex => $data) {

        //     switch($dataIndex) {
        //         case 'f':
        //             $processedDataArr['vend_id'] = substr($data, strpos($data, "=") + 1);
        //             break;
        //         case 't':
        //             break;
        //         case 'm':
        //             break;
        //         case 'g':
        //             break;
        //         case 'p':
        //             $processedDataArr['content'] = substr($data, -1) == '!' ? base64_decode(substr_replace($data,"=",-1)) : base64_decode($data);
        //             break;
        //         default:
        //     }
        // }
        if($input = $this->input) {
            $vendData = VendData::create([
                'value' => $input,
            ]);

            if($vid = $input['Vid']) {
                $vend = Vend::firstOrCreate([
                    'code' => $vid,
                ]);
                $coinAmount = isset($input['CoinCnt']) ? $input['CoinCnt'] : null;
                $firmwareVer = isset($input['Ver']) ? (int)$input['Ver'] : null;
                $isDoorOpen = isset($input['isDoorOpen']) ? $input['isDoorOpen'] : null;
                $isSensorNormal = isset($input['isDoorOpen']) ? $input['Sensor'] : null;

                if($vend) {
                    if($coinAmount) {
                        $vend->coin_amount = $coinAmount;
                    }

                    if($firmwareVer) {
                        $vend->firmware_ver = $firmwareVer;
                    }

                    if($isDoorOpen) {
                        $vend->is_door_open = $isDoorOpen == 'open' ? true : false;
                    }

                    if($isSensorNormal) {
                        $vend->is_sensor_normal = $isSensorNormal;
                    }

                    $vend->save();

                    if($type = $input['Type']) {
                        switch($type) {
                            case 'VENDER':
                                if($temp = $input['TEMP']) {
                                    $this->createVendTemp($vend, $temp);
                                }
                                break;
                            case 'TRADE':
                                $this->createVendTransaction($vend);
                                break;
                            case 'CHANNEL':
                                $this->syncVendChannels($vend);
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
                $createdTemp = $vend->vendTemps()->create([
                    'value' => $temp,
                ]);

                // $prevIsKeepVendTemp = VendTemp::where('vend_id', $vend->id)->where('is_keep', true)->latest()->first();

                // if(!$prevIsKeepVendTemp or ($prevIsKeepVendTemp and $prevIsKeepVendTemp->created_at->addMinutes(5)->isPast())) {
                //     $createdTemp->update(['is_keep' => true]);
                // }

                $vend->temp = $temp;
                $vend->temp_updated_at = Carbon::now();
                $vend->is_temp_error = false;
            }

            $vend->save();
        }
    }


    private function createVendTransaction(Vend $vend)
    {
        $input = $this->input;

        $paymentMethod = PaymentMethod::where('code', $input['PAY_TYPE'])->first();

        if($sID = $input['SId']) {
            $vendChannel = VendChannel::where('code', $sID)->where('vend_id', $vend->id)->first();

            if(!$vendChannel) {
                $vendChannel = VendChannel::create([
                    'code' => $sID,
                    'vend_id' => $vend->id,
                ]);
            }
        }

        $vendChannelError = VendChannelError::where('code', (isset($input['SErr']) ? $input['SErr'] : 0))->where('code', '!=', 0)->first();

        VendTransaction::create([
            'order_id' => $input['ORDRID'],
            'transaction_datetime' => Carbon::createFromFormat('Y-m-d H:i:s', $input['TIME']),
            'amount' => $input['Price'],
            'payment_method_id' => $paymentMethod ? $paymentMethod->id : null,
            'vend_id' => $vend->id,
            'vend_channel_id' => isset($vendChannel) ? $vendChannel->id : 0,
            'vend_channel_error_id' => isset($vendChannelError) ? $vendChannelError->id : null
        ]);

        if($vendChannelError) {
            $this->syncVendChannelErrorLog($vend, $input['SId'], $input['sErr']);
        }
    }

    private function syncVendChannels(Vend $vend)
    {
        $input = $this->input;

        if($channels = $input['channels']) {
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
