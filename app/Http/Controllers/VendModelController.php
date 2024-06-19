<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendModelResource;
use App\Models\VendModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendModelController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'name',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        return Inertia::render('VendModel/Index', [
            'vendModels' => VendModelResource::collection(
                VendModel::query()
                    ->with('vends')
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

        VendModel::create($request->all());

        return redirect()->route('vend-models');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $model = VendModel::findOrFail($id);
        $model->update($request->all());

        return redirect()->route('vend-models');
    }

    public function delete($id)
    {
        $model = VendModel::findOrFail($id);
        $model->delete();

        return redirect()->route('vend-models');
    }
}
