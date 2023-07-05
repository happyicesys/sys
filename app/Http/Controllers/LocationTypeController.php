<?php

namespace App\Http\Controllers;

use App\Http\Resources\LocationTypeResource;
use App\Models\LocationType;
use App\Traits\HasWeightage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LocationTypeController extends Controller
{
    use HasWeightage;

    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('LocationType/Index', [
            'locationTypes' => LocationTypeResource::collection(
                LocationType::query()
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        LocationType::create($request->all());

        return redirect()->route('location-types');
    }

    public function update(Request $request, $locationTypeId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $locationType = LocationType::findOrFail($locationTypeId);
        $locationType->update($request->all());

        $this->recalculateAllWeightage(get_class($locationType));

        return redirect()->route('location-types');
    }

    public function delete($locationTypeId)
    {
        $locationType = LocationType::findOrFail($locationTypeId);
        $locationType->delete();

        return redirect()->route('location-types');
    }
}
