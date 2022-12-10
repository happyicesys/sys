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
use Carbon\Carbon;
use Illuminate\Http\Request;
use Log;

class VendDataController extends Controller
{
    public function create(Request $request)
    {
        $input = $request->all();
        $ipAddress = $request->ip();

        ProcessVendData::dispatch($input, $ipAddress);

        $input = collect($input);
        if($input->has('f') and $input->has('g') and $input->has('m') and $input->has('p') and $input->has('t')) {
            return $input['f'].',4,MQ==';
        }
        return true;
    }
}
