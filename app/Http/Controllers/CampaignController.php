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
                    ->with(['operator', 'labelsX', 'labelsY'])
                    ->filterIndex($request)
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
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
                Tag::orderBy('name')->get()
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
            'promo_type' => 'required|string|in:' . implode(',', array_keys(Campaign::TYPES_MAPPING)),
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

        $payload = [
            'name' => $validated['name'],
            'operator_id' => $validated['operator_id'] ?? auth()->user()->operator_id,
            'is_active' => true,
            'slug' => $validated['slug'] ?? null,
            'description' => $validated['description'] ?? null,
            'promo_type' => $validated['promo_type'],
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
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->mapWithKeys(fn ($id) => [$id => ['type' => 'x']])
            ->toArray();

        $labelsYPivot = collect($validated['labels_y'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->mapWithKeys(fn ($id) => [$id => ['type' => 'y']])
            ->toArray();

        if ($validated['promo_type'] === Campaign::TYPE_ITEM) {
            if (empty($labelsYPivot) && !empty($labelsXPivot)) {
                $labelsYPivot = collect(array_keys($labelsXPivot))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->mapWithKeys(fn ($id) => [$id => ['type' => 'y']])
                    ->toArray();
            }

            $labelsXPivot = [];
        }

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
                Tag::orderBy('name')->get()
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
            'promo_type' => 'required|string|in:' . implode(',', array_keys(Campaign::TYPES_MAPPING)),
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

        $campaign->update([
            'name' => $validated['name'],
            'operator_id' => $validated['operator_id'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'promo_type' => $validated['promo_type'],
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
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->mapWithKeys(fn ($id) => [$id => ['type' => 'x']])
            ->toArray();

        $labelsYPivot = collect($validated['labels_y'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->mapWithKeys(fn ($id) => [$id => ['type' => 'y']])
            ->toArray();

        if ($validated['promo_type'] === Campaign::TYPE_ITEM) {
            if (empty($labelsYPivot) && !empty($labelsXPivot)) {
                $labelsYPivot = collect(array_keys($labelsXPivot))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->mapWithKeys(fn ($id) => [$id => ['type' => 'y']])
                    ->toArray();
            }

            $labelsXPivot = [];
        }

        $campaign->labelsX()->sync($labelsXPivot);
        $campaign->labelsY()->sync($labelsYPivot);

        return redirect()->route('campaigns');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Campaign $campaign)
    {
        //
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
