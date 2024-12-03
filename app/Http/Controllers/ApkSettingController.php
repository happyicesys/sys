<?php

namespace App\Http\Controllers;

use App\Models\ApkSetting;
use App\Models\Operator;
use App\Models\Vend;
use App\Models\VendPrefix;
use App\Http\Resources\ApkSettingResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendPrefixResource;
use App\Services\VendParameterService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApkSettingController extends Controller
{
    protected $vendParameterService;

    public function __construct()
    {
        $this->vendParameterService = new VendParameterService();
    }

    public function index(Request $request)
    {
        $request->merge([
            'sortKey' => $request->sortKey ? $request->sortKey : 'created_at',
            'sortBy' => $request->sortBy ? $request->sortBy : 'false',
        ]);

        $apkSettings = ApkSetting::query()
            ->with([
                'vends.customer'
            ])
            ->filterIndex($request)
            ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
            ->withQueryString();

        return Inertia::render('ApkSetting/Index', [
            'apkSettings' => ApkSettingResource::collection($apkSettings),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
        ]);
    }

    public function create()
    {
        return Inertia::render('ApkSetting/Create', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'settings_parameter_json' => $this->vendParameterService->getCampaignParameter($this->vendParameterService->getDefaultParameter())
        ]);

        $apkSetting = ApkSetting::create($request->all());

        return redirect()->route('apk-settings.edit', [$apkSetting->id]);
    }

    public function edit(Request $request, $id)
    {
        $apkSetting = ApkSetting::query()
            ->with([
                'images',
                'vends.customer',
                'videos',
            ])
            ->findOrFail($id);

        return Inertia::render('ApkSetting/Edit', [
            'apkSetting' => ApkSettingResource::make($apkSetting),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'unbindedVendOptions' => VendResource::collection(
                Vend::with([
                    'customer'
                ])
                ->doesntHave('apkSettingVend')
                ->where('is_active', true)
                ->select(
                    'id',
                    'code',
                    'customer_id',
                    'name',
                )
                ->orderBy('code')
                ->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
        ]);
    }

    public function update(Request $request, $id)
    {
        // dd($request->all(), $id);
        $apkSetting = ApkSetting::findOrFail($id);

        $request->merge([
            'settings_parameter_json' => $this->vendParameterService->getCampaignParameter($request->all())
        ]);

        $apkSetting->fill($request->all());
        $apkSetting->save();

        $apkSetting->vends()->sync($request->vends);

        // if($request->vends) {
        //     $apkSetting->vends()->delete();
        //     foreach($request->vends as $vendID) {
        //         $vend = Vend::findOrFail($vendID);
        //         $vend->apkSettingVend()->create([
        //             'apk_setting_id' => $apkSetting->id,
        //         ]);
        //     }
        //  }

        return redirect()->route('apk-settings.edit', [$apkSetting->id]);
    }
}
