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
            'sortKey' => $request->sortKey ? $request->sortKey : 'vend_config_name',
            'sortBy' => isset($request->sortBy) && $request->sortBy !== null && $request->sortBy !== '' ? $request->sortBy : false,
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
                    ->when($request->product_mapping_id, function ($query, $search) {
                        if ($search !== 'all') {
                            $query->where('product_mapping_id', $search);
                        }
                    })
                    ->when($request->vend_config_id, function ($query, $search) {
                        if ($search !== 'all') {
                            $query->whereHas('vendConfigs', function ($query) use ($search) {
                                $query->where('vend_configs.id', $search);
                            });
                        }
                    })
                    ->when($request->vendPrefixes, function ($query, $search) {
                        if ($search !== 'all') {
                            if (in_array('single-ud', $search)) {
                                $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                                unset($search[array_search('single-ud', $search)]);
                            }
                            $query->whereIn('id', $search);
                        }
                    })
                    ->when($request->vendStatus, function ($query, $search) {
                        if ($search != 'all') {
                            $query->whereHas('vends', function ($query) use ($search) {
                                switch ($search) {
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
                    ->when($request->sortKey, function ($query, $search) use ($request) {
                        $sortDirection = filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';
                        if ($search === 'vend_config_name') {
                            $query->orderBy(
                                \App\Models\VendConfig::select('name')
                                    ->join('vend_config_vend_prefix', 'vend_configs.id', '=', 'vend_config_vend_prefix.vend_config_id')
                                    ->whereColumn('vend_config_vend_prefix.vend_prefix_id', 'vend_prefixes.id')
                                    ->orderBy('name')
                                    ->limit(1),
                                $sortDirection
                            );
                        } elseif ($search === 'product_mapping_name') {
                            $query->orderBy(
                                \App\Models\ProductMapping::select('product_mappings.name')
                                    ->join('product_mapping_vend_prefix', 'product_mappings.id', '=', 'product_mapping_vend_prefix.product_mapping_id')
                                    ->whereColumn('product_mapping_vend_prefix.vend_prefix_id', 'vend_prefixes.id')
                                    ->whereNotIn('product_mappings.id', function ($q) {
                                        $q->select('pm_pm.upcoming_product_mapping_id')
                                            ->from('product_mapping_product_mapping as pm_pm')
                                            ->join('product_mapping_vend_prefix as pmvp2', 'pm_pm.product_mapping_id', '=', 'pmvp2.product_mapping_id')
                                            ->whereColumn('pmvp2.vend_prefix_id', 'vend_prefixes.id');
                                    })
                                    ->orderBy('product_mappings.name')
                                    ->limit(1),
                                $sortDirection
                            );
                        } elseif ($search === 'upcoming_product_mapping_name') {
                            $query->orderBy(
                                \App\Models\ProductMapping::select('product_mappings.name')
                                    ->join('product_mapping_product_mapping', 'product_mappings.id', '=', 'product_mapping_product_mapping.upcoming_product_mapping_id')
                                    ->join('product_mapping_vend_prefix', 'product_mapping_product_mapping.product_mapping_id', '=', 'product_mapping_vend_prefix.product_mapping_id')
                                    ->whereColumn('product_mapping_vend_prefix.vend_prefix_id', 'vend_prefixes.id')
                                    ->orderBy('product_mappings.name')
                                    ->limit(1),
                                $sortDirection
                            );
                        } else {
                            $query->orderBy($search, $sortDirection);
                        }
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

        $vendPrefix = VendPrefix::create($request->all());

        $productMappingIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('productMappings', [])),
            fn($id) => $id > 0
        )));

        if ($productMappingIds) {
            $vendPrefix->productMappings()->sync($productMappingIds);
        }

        $upcomingProductMappingIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('upcomingProductMappings', [])),
            fn($id) => $id > 0
        )));

        $this->syncUpcomingProductMappings($productMappingIds, $upcomingProductMappingIds);

        return redirect()->route('vend-prefixes');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $model = VendPrefix::findOrFail($id);

        $model->update($request->all());

        $existingProductMappingIds = $model->productMappings()->pluck('product_mappings.id')->all();

        $productMappingIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('productMappings', [])),
            fn($id) => $id > 0
        )));

        $model->productMappings()->sync($productMappingIds);

        $upcomingProductMappingIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('upcomingProductMappings', [])),
            fn($id) => $id > 0
        )));

        $this->syncUpcomingProductMappings($productMappingIds, $upcomingProductMappingIds);

        $removedProductMappings = array_diff($existingProductMappingIds, $productMappingIds);

        if ($removedProductMappings) {
            $this->syncUpcomingProductMappings($removedProductMappings, []);
        }

        // validate if product mapping is no longer in vend prefix, unmap the product mapping id of vend
        $vends = $model->vends;
        foreach ($vends as $vend) {
            if ($vend->productMapping and $vend->vendPrefix) {
                // check whether vendPrefix is in productMapping, if not, unmap the product_mapping_id of vend
                $productMapping = $vend->productMapping;
                $vendPrefixes = $productMapping->vendPrefixes;
                if (!in_array($model->id, $vendPrefixes->pluck('id')->toArray())) {
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

    protected function syncUpcomingProductMappings(array $productMappingIds, array $upcomingProductMappingIds): void
    {
        $productMappingIds = array_filter(
            array_map('intval', $productMappingIds),
            fn($id) => $id > 0
        );

        foreach ($productMappingIds as $productMappingId) {
            $productMapping = ProductMapping::find($productMappingId);

            if (!$productMapping) {
                continue;
            }

            $productMapping->upcomingProductMappings()->sync($upcomingProductMappingIds);
        }
    }
}
