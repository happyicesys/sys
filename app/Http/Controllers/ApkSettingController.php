<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApkSettingRequest;
use App\Http\Requests\UpdateApkSettingRequest;
use App\Http\Resources\ApkSettingResource;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendResource;
use App\Jobs\Vend\PushApkSettingSync;
use App\Models\ApkSetting;
use App\Models\ApkSettingVend;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\Operator;
use App\Models\Tag;
use App\Models\Vend;
use App\Models\VendPrefix;
use App\Services\TagBindingService;
use App\Services\VendJobService;
use App\Services\VendParameterService;
use App\Services\VendPricingSourceService;
use App\ValueObjects\ApkSettingParameters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ApkSettingController extends Controller
{
    protected $tagBindingService;

    protected $vendParameterService;

    protected $vendJobService;

    protected $vendPricingSourceService;

    public function __construct(VendJobService $vendJobService, VendPricingSourceService $vendPricingSourceService)
    {
        $this->tagBindingService = new TagBindingService;
        $this->vendParameterService = new VendParameterService;
        $this->vendJobService = $vendJobService;
        $this->vendPricingSourceService = $vendPricingSourceService;
    }

    public function index(Request $request)
    {
        $request->merge([
            'sortKey' => $request->sortKey ? $request->sortKey : 'created_at',
            'sortBy' => $request->sortBy ? $request->sortBy : 'false',
        ]);

        $apkSettings = ApkSetting::query()
            ->with([
                'vends.customer',
                // Lightweight load for the "Campaign Name" column on the list.
                // Only id/name/is_active are needed; full campaign data is
                // loaded separately on the edit page.
                'campaigns:id,name,is_active',
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

        // Campaign items ship inside /parameters — reflect them straight away.
        PushApkSettingSync::schedule($apkSetting->id);
    }

    public function bindCampaigns(Request $request, $id)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        $validated = $request->validate([
            'campaign_ids' => ['required', 'array', 'min:1'],
            'campaign_ids.*' => ['integer', 'exists:campaigns,id'],
        ]);

        $campaignIds = collect($validated['campaign_ids'])
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! count($campaignIds)) {
            return redirect()->back();
        }

        $apkSetting->campaigns()->syncWithoutDetaching($campaignIds);

        PushApkSettingSync::schedule($apkSetting->id);

        return redirect()->route('apk-settings.edit', [$apkSetting->id]);
    }

    public function deleteCampaignItem($id)
    {
        $campaignItem = CampaignItem::findOrFail($id);

        // Captured before the delete — the row is gone afterwards.
        $apkSettingId = $campaignItem->apk_setting_id;

        if ($campaignItem->tagBindings) {
            foreach ($campaignItem->tagBindings as $tagBinding) {
                $tagBinding->delete();
            }
        }
        $campaignItem->delete();

        PushApkSettingSync::schedule($apkSettingId);
    }

    public function store(StoreApkSettingRequest $request)
    {
        $request->merge([
            'settings_parameter_json' => $this->vendParameterService->getCampaignParameter($this->vendParameterService->getDefaultParameter()),
        ]);

        $apkSetting = ApkSetting::create($request->all());

        return redirect()->route('apk-settings.edit', [$apkSetting->id]);
    }

    public function unbindCampaign($id, $campaignId)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        $apkSetting->campaigns()->detach($campaignId);

        // NOTE: a v302-or-older machine keeps running its last campaign when
        // the list becomes empty (the APK clears its campaign array only inside
        // a "response non-empty" guard — see APK_SETTINGS_WIRING_AUDIT.md fix
        // #6). The push is still correct here; the APK-side fix is what makes
        // "switch the last campaign off" work end to end.
        PushApkSettingSync::schedule($apkSetting->id);

        return redirect()->route('apk-settings.edit', [$apkSetting->id]);
    }

    public function edit(Request $request, $id)
    {
        $apkSetting = ApkSetting::query()
            ->with([
                'campaignImages',
                'campaignMedia',
                'campaignVideos',
                'defaultMedia',
                'images',
                'campaigns.operator',
                'campaigns.labelsX',
                'campaigns.labelsY',
                'campaignItems.tagBindings.tag',
                'vends.customer',
                'videos',
            ])
            ->findOrFail($id);

        // Fleet readiness for the "Deprecated" section: deprecated parameters
        // are retired once every bound machine that actually FETCHES the
        // settings payload reports an APK at or above the threshold. A vend
        // that never reported a version counts as not-ready (conservative).
        // Small-board machines (13x versionCode stream) never call
        // /parameters, so they can't block retirement — but below 140 the
        // stream is a heuristic (see Vend::maybeSmallBoardStream), so they
        // are surfaced separately rather than silently excluded.
        $deprecationThreshold = ApkSettingParameters::DEPRECATION_FLEET_APK_VERSION;
        // Readiness must cover the WHOLE bound fleet, not just the viewer's
        // operator slice — retiring a key on a scoped count would reset prefs
        // on another operator's pre-301 machines.
        $boundVends = Vend::withoutGlobalScopes()
            ->whereIn('id', ApkSettingVend::where('apk_setting_id', $apkSetting->id)->pluck('vend_id'))
            ->get();
        $readyVends = $boundVends->filter(
            fn ($vend) => $vend->reportedApkVersion() >= $deprecationThreshold
        )->count();
        $maybeSmallBoard = $boundVends->filter(
            fn ($vend) => $vend->maybeSmallBoardStream()
        )->count();

        $uiSchema = ApkSettingParameters::uiSchema();

        return Inertia::render('ApkSetting/Edit', [
            'apkSetting' => ApkSettingResource::make($apkSetting),
            'deprecation' => [
                'threshold' => $deprecationThreshold,
                'readyVends' => $readyVends,
                'totalVends' => $boundVends->count(),
                'maybeSmallBoard' => $maybeSmallBoard,
                // Labeled from the registry so the page never restates
                // labels/groups by hand or by key-name convention.
                'keys' => collect(ApkSettingParameters::deprecatedKeys())
                    ->map(fn (string $key) => [
                        'key' => $key,
                        'label' => $uiSchema[$key]['label'],
                        'group' => $uiSchema[$key]['group'],
                        'note' => $uiSchema[$key]['deprecated']['note'] ?? null,
                    ])->values(),
            ],
            'campaignOptions' => CampaignResource::collection(
                Campaign::with(['operator'])->orderBy('name')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productTagOptions' => TagResource::collection(
                // Campaign-item labels are Product-scoped tags; filter by
                // classname so Customer/Site tags don't appear here.
                Tag::where('classname', \App\Models\Product::class)->orderBy('name')->get()
            ),
            'unbindedVendOptions' => VendResource::collection(
                Vend::with([
                    'customer',
                ])
                    ->doesntHave('apkSettingVend')
                    ->where(function ($query) {
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

        if ($apkSetting->vends) {
            foreach ($apkSetting->vends as $vend) {
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

        if ($apkSetting) {
            $apkSettingVend = ApkSettingVend::findOrFail($apkSetting->id);
            $apkSettingVend->delete();
        }

        // Straight to this one machine: it is no longer in any setting's vend
        // list, so the debounced fan-out cannot reach it. Note the machine will
        // now get a 400 from /parameters and keep its last-known settings —
        // that is the existing unbound behaviour, not something this adds.
        $this->pushToVendNow($vend);

        return redirect()->back();
    }

    /**
     * Immediate single-machine nudge, for the cases the debounced fan-out
     * cannot serve (a vend that has just been unbound). Never let a push
     * failure break the CMS action the user actually asked for.
     */
    private function pushToVendNow($vend): void
    {
        try {
            $this->vendJobService->syncSettingsToVend($vend);
        } catch (\Throwable $e) {
            Log::warning('apk-setting push to vend failed', [
                'vend' => $vend instanceof Vend ? $vend->code : $vend,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Per-machine pricing source, edited from the bound-machines table on this
     * page. The setting's own selectedPricingSource is NOT the switch: it is
     * shared by every bound machine, whereas "follow the Site's pricing" is a
     * per-machine decision (Vend::usesServerPrice()) — /parameters derives the
     * wire value from the vend, and VendPricingSourceObserver nudges the terminal.
     */
    public function updateVendPricingSource(Request $request, $vendId)
    {
        $request->validate([
            'is_using_server_price' => 'required|boolean',
        ]);

        $vend = Vend::findOrFail($vendId);

        $this->vendPricingSourceService->setUsingServerPrice(
            $vend,
            $request->boolean('is_using_server_price')
        );

        return redirect()->back();
    }

    public function update(UpdateApkSettingRequest $request, $id)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        // dd($request->all());
        $request->merge([
            // merge, NOT getCampaignParameter - see SettingController::updateParameter.
            'settings_parameter_json' => $this->vendParameterService->mergeCampaignParameter(
                $apkSetting->settings_parameter_json,
                $request->all()
            ),
        ]);

        $apkSetting->fill($request->all());
        $apkSetting->save();

        // Bindings are only touched when the form posts an actual vends ARRAY —
        // an absent key or an explicit null (scripted call, partial post) must
        // mean "leave bindings alone", not "detach the whole fleet"
        // (sync(null) == sync([])).
        if (is_array($request->input('vends'))) {
            // The vends() relation is operator-scoped, but sync() diffs the RAW
            // pivot. A non-HappyIce user never sees other operators' bindings in
            // the form, so merge those hidden bindings back into the target or
            // this save would silently detach them (and, being invisible to the
            // scoped snapshot, they'd miss their re-read nudge too).
            $visibleBound = $apkSetting->vends()->pluck('vends.id')->all();
            $rawBound = ApkSettingVend::where('apk_setting_id', $apkSetting->id)
                ->pluck('vend_id')->all();
            $hiddenBound = array_diff($rawBound, $visibleBound);

            $target = array_values(array_unique(array_merge(
                $hiddenBound,
                collect($request->vends)->filter()->all()
            )));

            // detached ⊆ visibleBound by construction, so the scoped lookup
            // below cannot miss any of them.
            $removed = $apkSetting->vends()->sync($target)['detached'];

            foreach (Vend::whereIn('id', $removed)->get() as $removedVend) {
                // Machines UNBOUND by this save are still told to re-read —
                // the debounced push below only reaches what stays bound.
                $this->pushToVendNow($removedVend);
            }
        }

        // Reflect the save (and any newly bound machine) without a manual Push.
        PushApkSettingSync::schedule($apkSetting->id);

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

        if ($request->files) {
            $files = $request->file('files');
            $dir = 'sys/vends/campaign-images';
            $storedPath = $files->storePublicly($dir);
            $fileName = pathinfo($files->getClientOriginalName(), PATHINFO_FILENAME);
            $url = Storage::url($storedPath);
            $apkSetting->videos()->create([
                'name' => $fileName,
                'type' => ApkSetting::FILE_TYPE_CAMPAIGN_IMAGE,
                'full_url' => $url,
                'local_url' => $dir.'/'.basename($storedPath),
            ]);
        }

        // Media changed: the machine re-downloads its banner/campaign
        // folders when it handles this nudge.
        PushApkSettingSync::schedule($apkSetting->id);

        return true;
    }

    public function uploadCampaignVideos(Request $request, $id)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        if ($request->files) {
            $files = $request->file('files');
            $dir = 'sys/vends/campaign-videos';
            $storedPath = $files->storePublicly($dir);
            $fileName = pathinfo($files->getClientOriginalName(), PATHINFO_FILENAME);
            $url = Storage::url($storedPath);
            $apkSetting->videos()->create([
                'name' => $fileName,
                'type' => ApkSetting::FILE_TYPE_CAMPAIGN_VIDEO,
                'full_url' => $url,
                'local_url' => $dir.'/'.basename($storedPath),
            ]);
        }

        // Media changed: the machine re-downloads its banner/campaign
        // folders when it handles this nudge.
        PushApkSettingSync::schedule($apkSetting->id);

        return true;
    }

    public function uploadImages(Request $request, $id)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        if ($request->files) {
            $files = $request->file('files');
            $dir = 'sys/vends/banner-images';
            $storedPath = $files->storePublicly($dir);
            $fileName = pathinfo($files->getClientOriginalName(), PATHINFO_FILENAME);
            $url = Storage::url($storedPath);
            $apkSetting->videos()->create([
                'name' => $fileName,
                'type' => ApkSetting::FILE_TYPE_IMAGE,
                'full_url' => $url,
                'local_url' => $dir.'/'.basename($storedPath),
            ]);
        }

        // Media changed: the machine re-downloads its banner/campaign
        // folders when it handles this nudge.
        PushApkSettingSync::schedule($apkSetting->id);

        return true;
    }

    public function uploadVideos(Request $request, $id)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        if ($request->files) {
            $files = $request->file('files');
            $dir = 'sys/vends/banner-videos';
            $storedPath = $files->storePublicly($dir);
            $fileName = pathinfo($files->getClientOriginalName(), PATHINFO_FILENAME);
            $url = Storage::url($storedPath);
            $apkSetting->videos()->create([
                'name' => $fileName,
                'type' => ApkSetting::FILE_TYPE_VIDEO,
                'full_url' => $url,
                'local_url' => $dir.'/'.basename($storedPath),
            ]);
        }

        // Media changed: the machine re-downloads its banner/campaign
        // folders when it handles this nudge.
        PushApkSettingSync::schedule($apkSetting->id);

        return true;
    }

    /**
     * Combined upload for the unified media sections: routes a picture or a
     * video to the SAME storage dir and attachment type the four per-kind
     * actions above use, so the legacy endpoints the deployed fleet polls
     * (banner-image / banner-video / campaign-*) are untouched by construction.
     */
    public function uploadMedia(Request $request, $id)
    {
        return $this->storeMediaUpload($request, $id, false);
    }

    public function uploadCampaignMedia(Request $request, $id)
    {
        return $this->storeMediaUpload($request, $id, true);
    }

    private function storeMediaUpload(Request $request, $id, bool $campaign)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        $file = $request->file('files');
        if (! $file) {
            return response(['error_message' => 'No file uploaded'], 422);
        }

        // Classify by EXTENSION, not MIME: the machine routes media through its
        // ImgFilter/VedioFilter extension lists, so extension is the on-device
        // truth. MIME sniffing both rejects valid-but-oddly-containered videos
        // (finfo says octet-stream) and accepts formats the APK silently skips
        // (webp, mkv) — which would ship as invisible orphans to every machine.
        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $isImage = in_array($ext, ['gif', 'jpg', 'jpeg', 'bmp', 'png'], true);
        $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'wmv'], true);
        if (! $isVideo && ! $isImage) {
            return response([
                'error_message' => 'Unsupported file type .'.$ext.' — machines play jpg/jpeg/png/gif/bmp and mp4/mov/avi/wmv only',
            ], 422);
        }

        // Mirror the caps the per-kind dropzones enforced client-side.
        $maxBytes = (int) (($isVideo ? 10 : 1.5) * 1024 * 1024);
        if ($file->getSize() > $maxBytes) {
            return response([
                'error_message' => $isVideo ? 'Video exceeds 10 MB' : 'Image exceeds 1.5 MB',
            ], 422);
        }

        if ($campaign) {
            $dir = $isVideo ? 'sys/vends/campaign-videos' : 'sys/vends/campaign-images';
            $type = $isVideo ? ApkSetting::FILE_TYPE_CAMPAIGN_VIDEO : ApkSetting::FILE_TYPE_CAMPAIGN_IMAGE;
            $relation = $apkSetting->campaignMedia();
        } else {
            $dir = $isVideo ? 'sys/vends/banner-videos' : 'sys/vends/banner-images';
            $type = $isVideo ? ApkSetting::FILE_TYPE_VIDEO : ApkSetting::FILE_TYPE_IMAGE;
            $relation = $apkSetting->defaultMedia();
        }

        // storePubliclyAs with the VALIDATED extension: plain storePublicly()
        // names the file via MIME guessExtension(), which turns an
        // oddly-containered mp4 (finfo: octet-stream) into "<hash>.bin" — an
        // extension the machine's ImgFilter/VedioFilter silently skips. The
        // extension we validated must be the extension we ship.
        $storedPath = $file->storePubliclyAs($dir, \Illuminate\Support\Str::random(40).'.'.$ext);
        $relation->create([
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'type' => $type,
            'full_url' => Storage::url($storedPath),
            'local_url' => $dir.'/'.basename($storedPath),
        ]);

        // Debounced on purpose: a multi-file dropzone sends one request per
        // file, and each push costs every bound machine a full media re-sync.
        PushApkSettingSync::schedule($apkSetting->id);

        return true;
    }

    public function destroy($id)
    {
        $apkSetting = ApkSetting::findOrFail($id);

        if ($apkSetting->vends()->exists()) {
            return back()->with('error', 'APK Setting is currently in use and cannot be deleted.');
        }

        $apkSetting->campaigns()->detach();

        foreach ($apkSetting->campaignItems as $campaignItem) {
            if ($campaignItem->tagBindings) {
                foreach ($campaignItem->tagBindings as $tagBinding) {
                    $tagBinding->delete();
                }
            }
            $campaignItem->delete();
        }

        foreach ($apkSetting->images as $image) {
            $image->delete();
        }

        foreach ($apkSetting->videos as $video) {
            $video->delete();
        }

        foreach ($apkSetting->campaignImages as $campaignImage) {
            $campaignImage->delete();
        }

        foreach ($apkSetting->campaignVideos as $campaignVideo) {
            $campaignVideo->delete();
        }

        $apkSetting->delete();

        return redirect()->back();
    }

    private function syncApkSettings($vendID)
    {
        // Delegates to the canonical implementation in VendJobService — the
        // payload/format used to be copy-pasted here and in VendController.
        $this->vendJobService->syncSettingsToVend(Vend::findOrFail($vendID));
    }
}
