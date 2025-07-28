<?php

namespace App\Http\Controllers;

use App\Jobs\PublishMqtt;
use App\Models\ApkSetting;
use App\Models\ApkSettingVend;
use App\Models\CampaignItem;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Tag;
use App\Models\Vend;
use App\Models\VendPrefix;
use App\Http\Resources\ApkSettingResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendPrefixResource;
use App\Services\TagBindingService;
use App\Services\VendParameterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ApkSettingController extends Controller
{
    protected $tagBindingService;
    protected $vendParameterService;

    public function __construct()
    {
        $this->tagBindingService = new TagBindingService();
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

    public function createCampaignItem(Request $request, $id)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        $campaignItemObj = $apkSetting->campaignItems()->create([
            'apk_setting_id' => $apkSetting->id,
            'qty' => $request->qty,
            'promo_type' => $request->promo_type,
            'value' => $request->value,
        ]);

        $this->tagBindingService->sync($campaignItemObj, $request->tags);
    }

    public function deleteCampaignItem($id)
    {
        $campaignItem = CampaignItem::findOrFail($id);

        if($campaignItem->tagBindings) {
            foreach($campaignItem->tagBindings as $tagBinding) {
                $tagBinding->delete();
            }
        }
        $campaignItem->delete();
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
                'campaignImages',
                'campaignVideos',
                'images',
                'campaignItems.tagBindings.tag',
                'vends.customer',
                'videos',
            ])
            ->findOrFail($id);

        $className = get_class(new Product());

        return Inertia::render('ApkSetting/Edit', [
            'apkSetting' => ApkSettingResource::make($apkSetting),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productTagOptions' => TagResource::collection(
                Tag::where('classname', $className)
                    ->orderBy('name')
                    ->get()
            ),
            'unbindedVendOptions' => VendResource::collection(
                Vend::with([
                    'customer'
                ])
                ->doesntHave('apkSettingVend')
                ->where(function($query) {
                    $query->orWhere('is_active', true)
                        ->orWhere('is_testing', true);
                })
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

    public function push(Request $request, $id)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        if($apkSetting->vends) {
            foreach($apkSetting->vends as $vend) {
                $this->syncApkSettings($vend->id);
            }
        }

        return redirect()->route('apk-settings.edit', [$apkSetting->id]);
    }

    public function unbindVend($vendID)
    {
        // dd($vendID);
        $vend = Vend::findOrFail($vendID);
        $apkSetting = $vend->apkSettingVend;

        if($apkSetting) {
            $apkSettingVend = ApkSettingVend::findOrFail($apkSetting->id);
            $apkSettingVend->delete();
        }

        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        // dd($request->all(), $id);
        $apkSetting = ApkSetting::findOrFail($id);

        // dd($request->all());
        $request->merge([
            'settings_parameter_json' => $this->vendParameterService->getCampaignParameter($request->all())
        ]);

        $apkSetting->fill($request->all());
        $apkSetting->save();

        $apkSetting->vends()->sync($request->vends);

        // $apkSetting->campaignItems()->delete();
        // if($request->campaignItems) {
        //     foreach($request->campaignItems as $campaignItem) {

        //     }
        // }

        // if($apkSetting->vends) {
        //     foreach($apkSetting->vends as $vend) {
        //         $this->syncApkSettings($vend->id);
        //     }
        // }

        return redirect()->route('apk-settings.edit', [$apkSetting->id]);
    }

    public function uploadCampaignImages(Request $request, $id)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        if($request->files) {
            $files = $request->file('files');
            $dir = 'sys/vends/campaign-images';
            $storedPath = $files->storePublicly($dir);
            $fileName = pathinfo($files->getClientOriginalName(), PATHINFO_FILENAME);
            $url = Storage::url($storedPath);
            $apkSetting->videos()->create([
                'name' => $fileName,
                'type' => ApkSetting::FILE_TYPE_CAMPAIGN_IMAGE,
                'full_url' => $url,
                'local_url' => $dir . '/' . basename($storedPath),
            ]);
        }
        return true;
    }

    public function uploadCampaignVideos(Request $request, $id)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        if($request->files) {
            $files = $request->file('files');
            $dir = 'sys/vends/campaign-videos';
            $storedPath = $files->storePublicly($dir);
            $fileName = pathinfo($files->getClientOriginalName(), PATHINFO_FILENAME);
            $url = Storage::url($storedPath);
            $apkSetting->videos()->create([
                'name' => $fileName,
                'type' => ApkSetting::FILE_TYPE_CAMPAIGN_VIDEO,
                'full_url' => $url,
                'local_url' => $dir . '/' . basename($storedPath),
            ]);
        }
        return true;
    }

    public function uploadImages(Request $request, $id)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        if($request->files) {
            $files = $request->file('files');
            $dir = 'sys/vends/banner-images';
            $storedPath = $files->storePublicly($dir);
            $fileName = pathinfo($files->getClientOriginalName(), PATHINFO_FILENAME);
            $url = Storage::url($storedPath);
            $apkSetting->videos()->create([
                'name' => $fileName,
                'type' => ApkSetting::FILE_TYPE_IMAGE,
                'full_url' => $url,
                'local_url' => $dir . '/' . basename($storedPath),
            ]);
        }
        return true;
    }

    public function uploadVideos(Request $request, $id)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        if($request->files) {
            $files = $request->file('files');
            $dir = 'sys/vends/banner-videos';
            $storedPath = $files->storePublicly($dir);
            $fileName = pathinfo($files->getClientOriginalName(), PATHINFO_FILENAME);
            $url = Storage::url($storedPath);
            $apkSetting->videos()->create([
                'name' => $fileName,
                'type' => ApkSetting::FILE_TYPE_VIDEO,
                'full_url' => $url,
                'local_url' => $dir . '/' . basename($storedPath),
            ]);
        }
        return true;
    }

    private function syncApkSettings($vendID)
    {
        $vend = Vend::findOrFail($vendID);

        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => 'TYPESYNCSETTINGSPARAM',
            'time' => Carbon::now()->timestamp,
            'action' => '',
            'mid' => $vend->code,
        ]));
        $contentLength = strlen($content);
        $key = $vend && $vend->private_key ? $vend->private_key : '123456789110138A';
        $md5 = md5($fid.','.$contentLength.','.$content.$key);

        PublishMqtt::dispatch('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5)->onQueue('high');
    }
}
