<?php

namespace App\Http\Controllers;

use App\Http\Resources\ZoneResource;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ZoneController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Zone/Index', [
            'zones' => ZoneResource::collection(
                Zone::query()
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

        Zone::create($request->all());

        return redirect()->route('zones');
    }

    public function update(Request $request, $zoneId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $zone = Zone::findOrFail($zoneId);
        $zone->update($request->all());

        return redirect()->route('zones');
    }

    public function delete($zoneId)
    {
        $zone = Zone::findOrFail($zoneId);
        $zone->delete();

        return redirect()->route('zones');
    }
}
