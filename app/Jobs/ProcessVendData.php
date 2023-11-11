<?php

namespace App\Jobs;

use App\Jobs\SaveVendChannelErrorLogsJson;
use App\Jobs\SaveVendChannelsJson;
use App\Jobs\SyncIsMqttVend;
use App\Models\LogData;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendChannelErrorLog;
use App\Models\VendData;
use App\Models\VendFan;
use App\Models\VendTemp;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;

class ProcessVendData implements ShouldQueue

{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $input;
    protected $ipAddress;
    protected $connectionType;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($input, $ipAddress, $connectionType)
    {
        $this->input = $input;
        $this->ipAddress = $ipAddress;
        $this->connectionType = $connectionType;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $connectionType = $this->connectionType;
        $jsonInput = '';

        if($connectionType === 'mqtt') {
            $input = [];
            foreach(explode('&', $this->input) as $processInput) {
                list($a, $b) = explode('=', $processInput);
                $input[$a] = $b;
            }
            $jsonInput = $input;
        }

        $input = collect($this->input);
        $processedDataArr = [];
        if($input->has('f') and $input->has('g') and $input->has('m') and $input->has('p') and $input->has('t')) {
            $processedDataArr['original'] = $input;
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
                if($processedDataArr['content'] === "{\"Type\":\"P\"}") {
                    if(isset($processedDataArr['code']) and $processedDataArr['code']) {
                        $vend = Vend::where('code', $processedDataArr['code'])->first();
                        if($vend) {
                            $this->vendSaveLastUpdatedTimeAndIpAddress($vend, $ipAddress);
                        }
                    }
                    $processedDataArr['data'] = null;

                }else {
                    $processedDataArr['data'] = json_decode($processedDataArr['content'], true);
                }
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
            $input = $processedDataArr['data'];
        }else {
            $input = $this->input;
        }

        if($input) {
            $vendData = VendData::create([
                'value' => $this->connectionType === 'mqtt' ? $jsonInput : $this->input,
                'ip_address' => $this->ipAddress,
                'connection' => $this->connectionType,
            ]);
            if($vendData) {
                $vend = Vend::where('code', $vendData->value['m'])->first();
                $this->logTempUpdatedAtVariance($vend, $vendData);

                if($this->connectionType === 'mqtt') {
                    $vend->update([
                        'is_mqtt' => true,
                        'mqtt_updated_at' => Carbon::now(),
                    ]);
                }
            }

            if(isset($input['Vid'])) {
                $vid = $input['Vid'];
                $vend = Vend::firstOrCreate([
                    'code' => $vid,
                ]);
                $coinAmount = isset($input['CoinCnt']) ? $input['CoinCnt'] : null;
                $firmwareVer = isset($input['Ver']) ? (int)$input['Ver'] : null;
                $isDoorOpen = isset($input['isDoorOpen']) ? $input['isDoorOpen'] : null;
                $isSensorNormal = isset($input['Sensor']) ? $input['Sensor'] : null;
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
                                $this->createVendFan($vend, $input);
                                $this->createVendTemp($vend, $input);
                                $this->saveParameter($vend, $input);
                                break;
                            case 'TRADE':
                                $this->createVendTransaction($vend, $input);
                                break;
                            case 'CHANNEL':
                                $this->syncVendChannels($vend, $input);
                                break;
                        }
                    }
                }
            }
        }
        return true;
    }

    private function createVendFan(Vend $vend, $input)
    {
        if(isset($input['fan']) and $input['fan']) {
            $vend->vendFans()->create([
                'value' => $input['fan'],
                'type' => VendFan::TYPE_MAIN,
            ]);
        }
    }

    private function createVendTemp(Vend $vend, $input)
    {
        // more than 3 minutes only update same machine temp
        // if(!$vend->temp_updated_at or $vend->temp_updated_at->addMinutes(2)->isPast()) {
            if($temp = $input['TEMP']) {
                if($temp == VendTemp::TEMPERATURE_ERROR) {
                    $vend->is_temp_error = true;
                }else {
                    $createdTemp = $vend->vendTemps()->create([
                        'value' => $temp,
                        'type' => VendTemp::TYPE_CHAMBER,
                    ]);

                    if(isset($input['t2'])) {
                        $tempEvaporator = $input['t2'];
                        $vend->vendTemps()->create([
                            'value' => $tempEvaporator,
                            'type' => VendTemp::TYPE_EVAPORATOR,
                        ]);
                    }

                    if(isset($input['t3'])) {
                        $temp3 = $input['t3'];
                        $vend->vendTemps()->create([
                            'value' => $temp3,
                            'type' => VendTemp::TYPE_THREE,
                        ]);
                    }

                    if(isset($input['t4'])) {
                        $temp4 = $input['t4'];
                        $vend->vendTemps()->create([
                            'value' => $temp4,
                            'type' => VendTemp::TYPE_FOUR,
                        ]);
                    }

                    $vend->temp = $temp;
                    $vend->is_temp_error = false;
                }
            }
            $vend->temp_updated_at = Carbon::now();
            $vend->save();
        // }
    }


    private function createVendTransaction(Vend $vend, $input)
    {
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

        $productId = null;
        if(isset($vendChannel) and $vendChannel and $vend->productMapping()->exists()) {
            $productMappingItem = $vend->productMapping->productMappingItems()->where('channel_code', $vendChannel->code)->first();
            if($productMappingItem) {
                $productId = $productMappingItem->product_id;
            }
        }

        $vendTransaction = VendTransaction::create(
            [
            'transaction_datetime' => Carbon::now(),
            'amount' => $input['Price'],
            'order_id' => $input['ORDRID'],
            'payment_method_id' => $paymentMethod ? $paymentMethod->id : null,
            'vend_id' => $vend->id,
            'vend_channel_id' => isset($vendChannel) ? $vendChannel->id : 0,
            'vend_channel_error_id' => isset($vendChannelError) ? $vendChannelError->id : null,
            'vend_transaction_json' => $input,
            'product_id' => $productId,
            ]
        );

        $this->syncVendTransactionTotalsJson($vendTransaction->vend);

        if($vendChannelError) {
            $this->syncVendChannelErrorLog($vend, $input['SId'], $input['SErr'], $vendTransaction->id);
        }
    }

    private function syncVendChannels(Vend $vend, $input)
    {
        if($channels = $input['channels']) {
            $vend->vendChannels()->update(['is_active' => false]);
            foreach($channels as $channel) {
                if($channel['capacity'] > 0 and $channel['channel_code'] >= 10 and $channel['channel_code'] <= 69) {
                    $vendChannel = VendChannel::updateOrCreate([
                        'vend_id' => $vend->id,
                        'code' => $channel['channel_code'],
                    ], [
                        'qty' => $channel['qty'],
                        'capacity' => $channel['capacity'],
                        'amount' => $channel['amount'],
                        'is_active' => true,
                    ]);
                    $this->syncVendChannelErrorLog($vend, $channel['channel_code'], $channel['error_code']);
                }else {
                    $vendChannelFalse = VendChannel::updateOrCreate([
                        'vend_id' => $vend->id,
                        'code' => $channel['channel_code'],
                    ], [
                        'qty' => $channel['qty'],
                        'capacity' => $channel['capacity'],
                        'amount' => $channel['amount'],
                        'is_active' => false,
                    ]);
                }
            }
            // dd($vend->vendChannels->toArray(), $vend->vendChannels()->sum('capacity'));
            SaveVendChannelsJson::dispatch($vend->id);
        }
    }

    private function syncVendChannelErrorLog(Vend $vend, $vendChannelCode, $vendChannelErrorCode, $vendTransactionId = null)
    {
        $vendChannelError = VendChannelError::where('code', $vendChannelErrorCode)->first();

        if($vendChannelError) {
            if($vendChannelError->code > 0) {
                $vendChannel = VendChannel::firstOrCreate([
                    'vend_id' => $vend->id,
                    'code' => $vendChannelCode,
                ]);

                $lastVendChannelErrorLog = $vendChannel->vendChannelErrorLogs()->latest()->first();

                // dd($vendChannel->toArray(), $lastVendChannelErrorLog->toArray(), $lastVendChannelErrorLog->vendChannelError->code, $vendChannelErrorCode, $lastVendChannelErrorLog->is_error_cleared);

                if(!$lastVendChannelErrorLog or ($lastVendChannelErrorLog->vendChannelError->code != $vendChannelErrorCode) or $lastVendChannelErrorLog->is_error_cleared == 1) {
                    $vendChannelErrorLog = VendChannelErrorLog::create([
                        'vend_channel_id' => $vendChannel->id,
                        'vend_channel_error_id' => $vendChannelError->id
                    ]);

                    if($vendTransactionId) {
                        $vendChannelErrorLog->vend_transaction_id = $vendTransactionId;
                        $vendChannelErrorLog->save();
                    }

                    if($lastVendChannelErrorLog and ($lastVendChannelErrorLog->vendChannelError->code != $vendChannelErrorCode)) {
                        $lastVendChannelErrorLog->is_error_cleared = true;
                        $lastVendChannelErrorLog->save();
                    }

                    if($vendChannelErrorLog and !$vendTransactionId) {
                        $this->logVendChannelErrorNotTally($vendChannelErrorLog);
                    }
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
            SaveVendChannelErrorLogsJson::dispatch($vend->id);
        }
    }

    private function saveParameter(Vend $vend, $input)
    {
        $vend->parameter_json = $input;
        $vend->save();
    }

    private function vendSaveLastUpdatedTimeAndIpAddress(Vend $vend, $ipAddress)
    {
        $vend->last_ip_address = $ipAddress;
        $vend->last_updated_at = Carbon::now();
        $vend->save();
    }

    private function syncVendTransactionTotalsJson(Vend $vend)
    {
        $vend->update([
            'vend_transaction_totals_json' => [
                'today_amount' => $vend->vendTodayTransactions->sum('amount'),
                'today_count' => $vend->vendTodayTransactions->count(),
                'yesterday_amount' => $vend->vendYesterdayTransactions->sum('amount'),
                'yesterday_count' => $vend->vendYesterdayTransactions->count(),
                'seven_days_amount' => $vend->vendSevenDaysTransactions->sum('amount'),
                'seven_days_count' => $vend->vendSevenDaysTransactions->count(),
                'thirty_days_amount' => $vend->vendThirtyDaysTransactions->sum('amount'),
                'thirty_days_count' => $vend->vendThirtyDaysTransactions->count(),
            ]
        ]);
    }

    private function logVendChannelErrorNotTally($vendChannelErrorLog)
    {
        $className = get_class(new VendChannelErrorLog());
        LogData::create([
            'value1' => $vendChannelErrorLog,
            'value2' => VendData::whereJsonContains('value->m', $vendChannelErrorLog->vendChannel->vend->code)
                                ->whereJsonContains('value->m', 'AAAA')
                                ->latest()
                                ->first(),
            'type' => $className,
        ]);
    }

    private function logTempUpdatedAtVariance($vend, $vendData)
    {
        $className = get_class(new VendTemp());

        if($vend and $vend->temp_updated_at and $vend->temp_updated_at->addMinutes(10)->isPast()) {
            LogData::create([
                'value1' => $vendData,
                'value2' => $vend,
                'type' => $className,
            ]);
        }
    }
}
