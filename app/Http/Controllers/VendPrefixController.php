<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\TelcoResource;
use App\Http\Resources\VendConfigResource;
use App\Http\Resources\VendPrefixResource;
use App\Models\Operator;
use App\Models\ProductMapping;
use App\Models\VendConfig;
use App\Models\VendPrefix;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendPrefixController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'name',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        return Inertia::render('VendPrefix/Index', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productMappingOptions' => ProductMappingResource::collection(
                ProductMapping::orderBy('name')->get()
            ),
            'vendConfigOptions' => VendConfigResource::collection(
                VendConfig::query()
                    ->orderBy('name')
                    ->get()
            ),
            'vendPrefixes' => VendPrefixResource::collection(
                VendPrefix::query()
                    ->with([
                        'operator',
                        'productMapping',
                        'vendConfigs.attachments',
                        'vends',
                    ])
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->product_mapping_id, function($query, $search) {
                        if($search !== 'all') {
                            $query->where('product_mapping_id', $search);
                        }
                    })
                    ->when($request->vend_config_id, function($query, $search) {
                        if($search !== 'all') {
                            $query->where('vend_config_id', $search);
                        }
                    })
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        VendPrefix::create($request->all());

        return redirect()->route('vend-prefixes');
    }

    public function update(Request $request, $id)
    {
        // dd($request->all());
        $request->validate([
            'name' => 'required',
        ]);

        $model = VendPrefix::findOrFail($id);

        $model->update($request->all());

        return redirect()->route('vend-prefixes');
    }

    public function delete($id)
    {
        $model = VendPrefix::findOrFail($id);
        $model->delete();

        return redirect()->route('vend-prefixes');
    }
}
