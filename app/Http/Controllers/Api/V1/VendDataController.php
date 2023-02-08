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
use App\Jobs\ProcessVendData;
use App\Services\VendDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Log;

class VendDataController extends Controller
{
    public function __construct(VendDataService $vendDataService)
    {
        $this->vendDataService = $vendDataService;
    }

    public function create(Request $request)
    {
        $input = $request->all();
        $ipAddress = $request->ip();

        // $vendDataService = $this->vendDataService->getVendData($input, $ipAddress, 'mqtt');
        // dd($vendDataService->toArray());

        ProcessVendData::dispatch($input, $ipAddress, 'http');

        $input = collect($input);
        $content = '';
        if($input->has('f') and $input->has('g') and $input->has('m') and $input->has('p') and $input->has('t')) {
            foreach($input as $dataIndex => $data) {
                switch($dataIndex) {
                    case 'f':
                        $fId = $data;
                        break;
                    case 'm':
                        $vendCode = $data;
                        break;
                    case 'p':
                        $content = substr($data, -1) == '!' ? base64_decode(substr_replace($data,"=",-1)) : base64_decode($data);
                        break;
                }
            }
            if(str_starts_with($content, "{\"") and $content !== "{\"Type\":\"P\"}") {
                $content = json_decode($content, true);
                if($content['Type'] === 'TIME') {
                    $vend = Vend::where('code', $vendCode)->first();
                    if($vend) {
                        if($vend->primaryOperator()->exists()) {
                            $operator = $vend->primaryOperator()->first();
                            $time = Carbon::now()->setTimezone($operator->timezone)->toDateTimeString();
                            $base64Encode = base64_encode('TIME'.$time);
                            $length = strlen($base64Encode);

                            return $fId.','.$length.','.$base64Encode;
                        }
                    }
                }
                // if($content['Type'])
            }
            return $input['f'].',4,MQ==';
        }
        return true;
    }
}
