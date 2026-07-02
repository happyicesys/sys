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
                        // DEPRECATED (2026-07): prefix→mapping binding retired — the
                        // Product Mapping column is hidden, so the pivot is no longer
                        // eager-loaded (VendPrefixResource guards with relationLoaded()).
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

        // DEPRECATED (2026-07): the prefix→mapping binding is retired. Product
        // mappings are assigned per-vend on the Setting page (all active mappings
        // selectable, no prefix gate). The product_mapping_vend_prefix pivot is
        // kept read-only for historical data — it must NOT be synced here.

        return redirect()->route('vend-prefixes');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $model = VendPrefix::findOrFail($id);

        $model->update($request->all());

        // DEPRECATED (2026-07): the prefix→mapping binding is retired. The old
        // sync + "unmap vends whose mapping left the prefix" cascade was removed:
        // with the Product Mapping field gone from the form, the request no longer
        // carries productMappings, and the old sync([]) here would have detached
        // every pivot row and nulled product_mapping_id / upcoming_product_mapping_id
        // on all vends of this prefix on any prefix edit (e.g. a rename).
        // The product_mapping_vend_prefix pivot is kept read-only for historical data.

        return redirect()->route('vend-prefixes');
    }

    public function delete($id)
    {
        $model = VendPrefix::withoutGlobalScopes()->findOrFail($id);

        if (!$model->operator_id) {
            return redirect()->route('vend-prefixes')->withErrors([
                'delete' => 'Global Machine Prefixes cannot be deleted.',
            ]);
        }

        $model->delete();

        return redirect()->route('vend-prefixes');
    }

    // DEPRECATED (2026-07): syncUpcomingProductMapping() was removed together with
    // the prefix→mapping binding. Upcoming mappings are managed on the Product
    // Mapping Edit page (ProductMappingController@update).
}
