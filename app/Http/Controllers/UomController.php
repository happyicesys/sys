<?php

namespace App\Http\Controllers;

use App\Http\Resources\UomResource;
use App\Models\Uom;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UomController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'sequence';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Uom/Index', [
            'uoms' => UomResource::collection(
                Uom::query()
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

        Uom::create($request->all());

        return redirect()->route('uoms');
    }

    public function update(Request $request, $uomId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $uom = Uom::findOrFail($uomId);
        $uom->update($request->all());

        return redirect()->route('uoms');
    }

    public function delete($uomId)
    {
        $uom = Uom::findOrFail($uomId);
        $uom->delete();

        return redirect()->route('uoms');
    }
}
