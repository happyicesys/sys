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
use App\Services\VendDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Log;

class VendDataController extends Controller
{
    public function __construct(VendDataService $vendDataService)
    {
        $this->vendDataService = $vendDataService;
    }

    public function create(Request $request)
    {
        // dd($request->all());
        $input = $request->all();
        $ipAddress = $request->ip();
        $connectionType = 'http';

        $standardizedVendData = $this->vendDataService->standardizedVendData($input, $connectionType);
        $decodedData = $this->vendDataService->decodeVendData($standardizedVendData);

        $response = $this->vendDataService->processVendData($standardizedVendData, $decodedData, $ipAddress, $connectionType);

        return $response;
    }

    public function getBindedVends()
    {
        $vends = Vend::with('latestVendBinding.customer')->has('latestVendBinding')->get();

        return $vends;
    }

    public function getVendMediaContent($code)
    {
        $vend = Vend::where('code', $code)->firstOrFail();

        return $vend->mediaContents;
    }

    public function uploadLog(Request $request, $id)
    {
        $request->validate([
            'file' => 'sometimes|max:10000',
        ]);
        $vend = Vend::where('code', $id)->firstOrFail();

        $url = Storage::url($request->file->storePublicly('sys/vends/logs'));
        $vend->logs()->create([
            'full_url' => $url,
            'local_url' => $url,
            'type' => Vend::ATTACHMENT_TYPE_LOG,
        ]);

        return true;
    }
}
