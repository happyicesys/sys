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
use Illuminate\Support\Facades\Cache;
use Log;

class VendDataController extends Controller
{
    private $vendDataService;

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

        // TEMP DEBUG: trace ACBVMCPA delivery for vend code 2004 only.
        // Remove once ACBVMCPA reception is confirmed.
        $isDebugVend = isset($input['m']) && (int) $input['m'] === 2004;
        if ($isDebugVend) {
            Log::info('SetPara2 incoming [vend 2004]', [
                'ip' => $ipAddress,
                'content_type' => $request->header('Content-Type'),
                'raw_body_len' => strlen($request->getContent()),
                'input_keys' => array_keys($input),
                'p_len' => strlen($input['p'] ?? ''),
                'p_last_30' => substr($input['p'] ?? '', -30),
            ]);
        }

        $standardizedVendData = $this->vendDataService->standardizedVendData($input, $connectionType);
        // dd($standardizedVendData);
        $decodedData = $this->vendDataService->decodeVendData($standardizedVendData);
        // dd($decodedData);

        if ($isDebugVend) {
            $decodedArr = is_array($decodedData) ? $decodedData : (method_exists($decodedData, 'toArray') ? $decodedData->toArray() : []);
            Log::info('SetPara2 decoded [vend 2004]', [
                'type' => $decodedArr['Type'] ?? '(no Type)',
                'decoded_keys' => array_keys($decodedArr),
            ]);
        }

        $response = $this->vendDataService->processVendData($standardizedVendData, $decodedData, $ipAddress, $connectionType);

        return $response;
    }

    public function getBindedVends()
    {
        // Real-time data - no caching (vend bindings need to be current)
        $columns = [
            'id',
            'code',
            'name',
            'operator_id',
            'customer_id',
            'vend_prefix_id',
        ];

        $vends = Vend::query()
            ->select($columns)
            ->whereNotNull('customer_id')
            ->with([
                'customer' => function ($query) {
                    $query->select([
                        'id',
                        'name',
                        'virtual_customer_code',
                        'virtual_customer_prefix',
                        'location_type_id',
                        'operator_id',
                    ]);
                },
            ])
            ->get();

        return $vends;
    }

    public function getVendMediaContent($code)
    {
        $cacheKey = "vend_media:{$code}";

        $imgUrl = Cache::remember($cacheKey, 600, function () use ($code) {
            $vend = Vend::with('mediaContents')->where('code', $code)->firstOrFail();
            return $vend->mediaContents->first()->full_url ?? null;
        });

        return $imgUrl;
    }

    public function uploadLog(Request $request, $id)
    {
        $request->validate([
            'file' => 'sometimes|max:512000',
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
