<?php

namespace App\Http\Controllers;


use App\Models\Campaign;
use App\Models\Operator;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\TagResource;
use App\Traits\GetUserTimezone;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;


class CampaignController extends Controller
{
    use GetUserTimezone;

    /**
     * Classname under which campaign labels (the "Labels X / Labels Y" tags)
     * live in the shared, polymorphic `tags` table. Campaign labels reuse the
     * Product scope — see CampaignSeeder::resolveTags() and the "Product
     * Labels" nav entry (/tags?classname=App\Models\Product).
     */
    private const CAMPAIGN_LABEL_CLASSNAME = 'App\\Models\\Product';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->merge([
            'date_from' => $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay(),
            'date_to' => $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay(),
            'operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id,
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : '100',
            'status' => $request->status ? $request->status : 'all',
            'sortBy' => $request->sortBy ? $request->sortBy : false,
            'sortKey' => $request->sortKey ? $request->sortKey : 'created_at',
        ]);

        return Inertia::render('Campaign/Index', [
            'campaigns' => CampaignResource::collection(
                Campaign::query()
                    ->select('campaigns.*')
                    ->selectSub(function ($query) {
                        $query->from('apk_setting_campaign')
                            ->join('apk_setting_vend', 'apk_setting_vend.apk_setting_id', '=', 'apk_setting_campaign.apk_setting_id')
                            ->whereColumn('apk_setting_campaign.campaign_id', 'campaigns.id')
                            ->selectRaw('COUNT(DISTINCT apk_setting_vend.vend_id)');
                    }, 'bound_machines_count')
                    ->with(['operator', 'labelsX', 'labelsY'])
                    ->filterIndex($request)
                    ->when($request->sortKey, function ($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function createView()
    {
        return Inertia::render('Campaign/Create', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'tagOptions' => TagResource::collection(
                // Campaign labels are stored as Product-scoped Tag rows (see
                // CampaignSeeder + the "Product Labels" nav at
                // /tags?classname=App\Models\Product). Without this classname
                // filter the dropdown also surfaced Customer/Site-scoped tags
                // (e.g. "Already Inform For Renewal"), which don't belong here.
                Tag::where('classname', self::CAMPAIGN_LABEL_CLASSNAME)
                    ->orderBy('name')
                    ->get()
            ),
            'promoTypeOptions' => $this->promoTypeOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function create()
    {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'operator_id' => 'required|integer|exists:operators,id',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'promo_type' => 'required|string|in:' . implode(',', Campaign::promoTypeValidationValues()),
            'is_using_qty' => 'required|string|in:qty,amount,both',
            'bundle_qty' => 'nullable|integer|min:1',
            'value' => 'nullable|numeric|min:0',
            'min_basket_value' => 'nullable|numeric|min:0',
            'max_discount_value' => 'nullable|numeric|min:0',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'remarks' => 'nullable|string|max:1000',
            'labels_x' => 'nullable|array',
            'labels_x.*' => 'integer|exists:tags,id',
            'labels_y' => 'nullable|array',
            'labels_y.*' => 'integer|exists:tags,id',
        ]);

        $promoType = Campaign::normalizePromoType($validated['promo_type']);

        $payload = [
            'name' => $validated['name'],
            'operator_id' => $validated['operator_id'] ?? auth()->user()->operator_id,
            'is_active' => true,
            'slug' => $validated['slug'] ?? null,
            'description' => $validated['description'] ?? null,
            'promo_type' => $promoType,
            'is_using_qty' => $validated['is_using_qty'],
            'bundle_qty' => $validated['bundle_qty'] ?? null,
            'value' => $validated['value'] ?? null,
            'min_basket_value' => $validated['min_basket_value'] ?? null,
            'max_discount_value' => $validated['max_discount_value'] ?? null,
            'start_at' => $validated['start_at'] ?? null,
            'end_at' => $validated['end_at'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ];

        $campaign = Campaign::create($payload);

        $labelsXPivot = collect($validated['labels_x'] ?? [])
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->mapWithKeys(fn($id) => [$id => ['type' => 'x']])
            ->toArray();

        $labelsYPivot = collect($validated['labels_y'] ?? [])
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->mapWithKeys(fn($id) => [$id => ['type' => 'y']])
            ->toArray();

        $campaign->labelsX()->sync($labelsXPivot);
        $campaign->labelsY()->sync($labelsYPivot);

        return redirect()->route('campaigns');
    }

    /**
     * Display the specified resource.
     */
    public function show(Campaign $campaign)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Campaign $campaign)
    {
        $campaign->load(['operator', 'labelsX', 'labelsY']);

        return Inertia::render('Campaign/Edit', [
            'campaign' => CampaignResource::make($campaign),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'tagOptions' => TagResource::collection(
                // Same Product-scoped filter as createView() — keep the edit
                // form's label dropdown limited to campaign labels only.
                Tag::where('classname', self::CAMPAIGN_LABEL_CLASSNAME)
                    ->orderBy('name')
                    ->get()
            ),
            'promoTypeOptions' => $this->promoTypeOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'operator_id' => 'required|integer|exists:operators,id',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'promo_type' => 'required|string|in:' . implode(',', Campaign::promoTypeValidationValues()),
            'is_using_qty' => 'required|string|in:qty,amount,both',
            'bundle_qty' => 'nullable|integer|min:1',
            'value' => 'nullable|numeric|min:0',
            'min_basket_value' => 'nullable|numeric|min:0',
            'max_discount_value' => 'nullable|numeric|min:0',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'remarks' => 'nullable|string|max:1000',
            'labels_x' => 'nullable|array',
            'labels_x.*' => 'integer|exists:tags,id',
            'labels_y' => 'nullable|array',
            'labels_y.*' => 'integer|exists:tags,id',
        ]);

        $promoType = Campaign::normalizePromoType($validated['promo_type']);

        $campaign->update([
            'name' => $validated['name'],
            'operator_id' => $validated['operator_id'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'promo_type' => $promoType,
            'is_using_qty' => $validated['is_using_qty'],
            'bundle_qty' => $validated['bundle_qty'] ?? null,
            'value' => $validated['value'] ?? null,
            'min_basket_value' => $validated['min_basket_value'] ?? null,
            'max_discount_value' => $validated['max_discount_value'] ?? null,
            'start_at' => $validated['start_at'] ?? null,
            'end_at' => $validated['end_at'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        $labelsXPivot = collect($validated['labels_x'] ?? [])
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->mapWithKeys(fn($id) => [$id => ['type' => 'x']])
            ->toArray();

        $labelsYPivot = collect($validated['labels_y'] ?? [])
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->mapWithKeys(fn($id) => [$id => ['type' => 'y']])
            ->toArray();

        $campaign->labelsX()->sync($labelsXPivot);
        $campaign->labelsY()->sync($labelsYPivot);

        return redirect()->route('campaigns');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Campaign $campaign)
    {
        if ($campaign->apkSettings()->exists()) {
            return back()->with('error', 'Campaign is currently in use and cannot be deleted.');
        }

        $campaign->labelsX()->detach();
        $campaign->labelsY()->detach();
        $campaign->delete();

        return redirect()->route('campaigns');
    }

    private function promoTypeOptions()
    {
        return collect(Campaign::TYPES_MAPPING)
            ->map(function ($label, $key) {
                return [
                    'id' => $key,
                    'name' => $label,
                ];
            })
            ->values();
    }
}
