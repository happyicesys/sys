<?php

namespace App\Http\Controllers;


use App\Models\Campaign;
use App\Models\Operator;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\OperatorResource;
use App\Traits\GetUserTimezone;
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
                    ->with(['operator'])
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
    public function create()
    {
        request()->validate([
            'name' => 'required|string|max:255',
            'operator_id' => 'nullable|integer|exists:operators,id',
            'is_active' => 'nullable|boolean',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
        ]);

        $payload = [
            'name' => request('name'),
            'operator_id' => request('operator_id') ?: auth()->user()->operator_id,
            'is_active' => filter_var(request('is_active', true), FILTER_VALIDATE_BOOLEAN),
            'slug' => request('slug'),
            'description' => request('description'),
            'start_at' => request('start_at'),
            'end_at' => request('end_at'),
            'remarks' => request('remarks'),
        ];

        Campaign::create($payload);

        return redirect()->route('campaigns');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Campaign $campaign)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Campaign $campaign)
    {
        //
    }
}
