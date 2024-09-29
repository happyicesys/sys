<?php

namespace App\Http\Controllers;

use App\Http\Resources\ModemTypeResource;
use App\Http\Resources\ModemUnitResource;
use App\Models\ModemType;
use App\Models\ModemUnit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ModemUnitController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'created_at',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        return Inertia::render('ModemUnit/Index', [
            'modemTypeOptions' => ModemTypeResource::collection(
                ModemType::query()
                    ->orderBy('id')
                    ->get()
            ),
            'modemUnits' => ModemUnitResource::collection(
                ModemUnit::query()
                    ->with(['modemType', 'vend'])
                    ->when($request->imei, function($query, $search) {
                        $query->where('imei', 'LIKE', "%{$search}%");
                    })
                    ->when($request->modem_type_id, function($query, $search) {
                        $query->where('modem_type_id', $search);
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
            'imei' => 'required',
        ]);

        ModemUnit::create($request->all());

        return redirect()->route('modem-units');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'imei' => 'required',
        ]);

        $model = ModemUnit::findOrFail($id);
        $model->update($request->all());

        return redirect()->route('modem-units');
    }

    public function delete($id)
    {
        $model = ModemUnit::findOrFail($id);
        $model->delete();

        return redirect()->route('modem-units');
    }
}
