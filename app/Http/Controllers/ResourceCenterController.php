<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResourceCenterResource;
use App\Models\ResourceCenter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResourceCenterController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission: read resource-centers']);
    }

    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('ResourceCenter/Index', [
            'resourceCenters' => ResourceCenterResource::collection(
                ResourceCenter::query()
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

        ResourceCenter::create($request->all());

        return redirect()->route('resource-centers');
    }

    public function update(Request $request, $resourceCenterId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $resourceCenter = ResourceCenter::findOrFail($resourceCenterId);
        $resourceCenter->update($request->all());

        return redirect()->route('resource-centers');
    }

    public function delete($resourceCenterId)
    {
        $resourceCenter = ResourceCenter::findOrFail($resourceCenterId);
        $resourceCenter->delete();

        return redirect()->route('resource-centers');
    }
}
