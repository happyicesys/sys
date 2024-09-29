<?php

namespace App\Http\Controllers;

use App\Http\Resources\ModemTypeResource;
use App\Models\ModemType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ModemTypeController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'id',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        return Inertia::render('ModemType/Index', [
            'modemTypes' => ModemTypeResource::collection(
                ModemType::query()
                    ->with('modemUnits')
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        ModemType::create($request->all());

        return redirect()->route('modem-types');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $model = ModemType::findOrFail($id);
        $model->update($request->all());

        return redirect()->route('modem-types');
    }

    public function delete($id)
    {
        $model = ModemType::findOrFail($id);
        $model->delete();

        return redirect()->route('modem-types');
    }
}
