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
            'vendStatus' => $request->vendStatus ? $request->vendStatus : 'active',
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'name',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        return Inertia::render('VendPrefix/Index', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productMappingOptions' => ProductMappingResource::collection(
                ProductMapping::select(['id', 'name'])->orderBy('name')->get()
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
                        'productMappings.upcomingProductMappings',
                        'vendConfigs.attachments',
                        'vends' => function ($query) use ($request) {
                            if ($request->vendStatus && $request->vendStatus !== 'all') {
                                switch ($request->vendStatus) {
                                    case 'disposed':
                                        $query->where('is_disposed', true);
                                        break;
                                    case 'factory':
                                        $query->where('is_testing', true);
                                        break;
                                    case 'active':
                                        $query->where('is_active', true);
                                        break;
                                    case 'inactive':
                                        $query->where('is_active', false);
                                        break;
                                }
                            }
                        },
                    ])
                    ->when($request->product_mapping_id, function($query, $search) {
                        if($search !== 'all') {
                            $query->where('product_mapping_id', $search);
                        }
                    })
                    ->when($request->vend_config_id, function($query, $search) {
                        if($search !== 'all') {
                            $query->whereHas('vendConfigs', function($query) use ($search) {
                                $query->where('vend_configs.id', $search);
                            });
                        }
                    })
                    ->when($request->vendPrefixes, function($query, $search) {
                        if($search !== 'all') {
                            if(in_array('single-ud', $search)) {
                                $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                                unset($search[array_search('single-ud', $search)]);
                            }
                            $query->whereIn('id', $search);
                        }
                    })
                    ->when($request->vendStatus, function($query, $search) {
                        if($search != 'all') {
                            $query->whereHas('vends', function($query) use ($search) {
                                switch($search) {
                                    case 'disposed':
                                        $query->where('is_disposed', true);
                                        break;
                                    case 'factory':
                                        $query->where('is_testing', true);
                                        break;
                                    case 'active':
                                        $query->where('is_active', true);
                                        break;
                                    case 'inactive':
                                        $query->where('is_active', false);
                                        break;
                                }
                            });
                        }
                    })
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::query()
                    ->orderBy('name')
                    ->get()
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
        $request->validate([
            'name' => 'required',
        ]);

        $model = VendPrefix::findOrFail($id);

        $model->update($request->all());

        $model->productMappings()->sync($request->productMappings);

        // validate if product mapping is no longer in vend prefix, unmap the product mapping id of vend
        $vends = $model->vends;
        foreach($vends as $vend) {
            if($vend->productMapping and $vend->vendPrefix) {
                // check whether vendPrefix is in productMapping, if not, unmap the product_mapping_id of vend
                $productMapping = $vend->productMapping;
                $vendPrefixes = $productMapping->vendPrefixes;
                if(!in_array($model->id, $vendPrefixes->pluck('id')->toArray())) {
                    $vend->product_mapping_id = null;
                    $vend->upcoming_product_mapping_id = null;
                    $vend->save();
                }
            }
        }

        return redirect()->route('vend-prefixes');
    }

    public function delete($id)
    {
        $model = VendPrefix::findOrFail($id);
        $model->delete();

        return redirect()->route('vend-prefixes');
    }
}
