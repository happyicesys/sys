<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\VendConfigResource;
use App\Models\Operator;
use App\Models\VendConfig;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendConfigController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('VendConfig/Index', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendConfigs' => VendConfigResource::collection(
                VendConfig::query()
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

        $vendConfig = VendConfig::create($request->all());

        return redirect()->route('vend-configs.edit', [$vendConfig->id]);
    }

    public function edit($id)
    {
        $model = VendConfig::with([
            'attachments',
            'operator',
            'vendPrefixes',
        ])->findOrFail($id);

        return Inertia::render('VendConfig/Edit', [
            'vendConfig' => VendConfigResource::make($model),
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $model = VendConfig::findOrFail($id);
        $model->update($request->all());

        return redirect()->route('vend-configs');
    }

    public function delete($id)
    {
        $model = VendConfig::findOrFail($id);
        $model->delete();

        return redirect()->route('vend-configs');
    }
}
