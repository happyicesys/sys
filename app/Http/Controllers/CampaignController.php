<?php

namespace App\Http\Controllers;


use App\Models\Campaign;
use App\Models\Operator;
use App\Models\Scopes\OperatorFilterScope;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\TagResource;
use App\Support\OperatorScope;
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
        // The Operator dropdown is an admin-only affordance (it is rendered
        // behind `admin-access vend-customers`, same as Vend/CustomerIndex).
        // Everyone else is pinned to their own operator scope regardless of
        // what arrives in the query string; Campaign::visibleTo() below is the
        // hard ceiling either way, so this only decides the default view.
        $request->merge([
            'date_from' => $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay(),
            'date_to' => $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay(),
            'operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id,
            'operators' => auth()->user()->can('admin-access vend-customers')
                ? OperatorScope::narrow($request->operators)
                : OperatorScope::current(),
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
                    ->visibleTo()
                    ->filterIndex($request)
                    ->when($request->sortKey, function ($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
            'operatorOptions' => OperatorResource::collection(
                $this->scopedOperators()
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
                $this->scopedOperators()
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

        // A valid operator id is not enough - it must be one this user may
        // act for. Without this, `exists:operators,id` happily accepts any
        // operator in the table and a campaign lands in someone else's tenancy.
        $this->authorizeOperator((int) $validated['operator_id']);

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
        $this->authorizeCampaign($campaign);

        $campaign->load(['operator', 'labelsX', 'labelsY']);

        return Inertia::render('Campaign/Edit', [
            'campaign' => CampaignResource::make($campaign),
            'operatorOptions' => OperatorResource::collection(
                $this->scopedOperators()
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
        $this->authorizeCampaign($campaign);

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

        // Guard the destination as well as the source, so an in-scope
        // campaign cannot be pushed out to another operator.
        $this->authorizeOperator((int) $validated['operator_id']);

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
        $this->authorizeCampaign($campaign);

        if ($campaign->apkSettings()->exists()) {
            return back()->with('error', 'Campaign is currently in use and cannot be deleted.');
        }

        $campaign->labelsX()->detach();
        $campaign->labelsY()->detach();
        $campaign->delete();

        return redirect()->route('campaigns');
    }

    /**
     * Operators the current user may pick from - drives every Operator
     * dropdown on this page. Mirrors Campaign::visibleTo(), so the form can
     * never offer a value the write guards would then reject.
     */
    private function scopedOperators()
    {
        // Drop OperatorFilterScope for the same reason OperatorScope does: it
        // keys off operator id 1, while the ceiling keys off the code 'HIPL'.
        // Leaving it on would collapse a HIPL user's dropdown to a single row
        // the moment those two ever stop agreeing.
        return Operator::withoutGlobalScope(OperatorFilterScope::class)
            ->whereIn('id', OperatorScope::current())
            ->orderBy('name')
            ->get();
    }

    /**
     * 403 unless the campaign belongs to an operator in the viewer's scope.
     * Route model binding resolves {campaign} by id alone, so without this a
     * user could reach another operator's campaign by typing the URL.
     */
    private function authorizeCampaign(Campaign $campaign): void
    {
        abort_unless(OperatorScope::allows((int) $campaign->operator_id), 403);
    }

    /**
     * 403 unless the given operator is one the viewer may write for.
     */
    private function authorizeOperator(int $operatorId): void
    {
        abort_unless(OperatorScope::allows($operatorId), 403);
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
