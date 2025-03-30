<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendConfigResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendSerialNumberResource;
use App\Http\Resources\VendResource;
use App\Models\VendConfig;
use App\Models\VendPrefix;
use App\Models\VendSerialNumber;
use App\Models\Vend;
use App\Models\VendModel;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;

class VendSerialNumberController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'code',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        return Inertia::render('VendSerialNumber/Index', [
            'vendSerialNumbers' => VendSerialNumberResource::collection(
                VendSerialNumber::query()
                    ->leftJoin('vends', 'vends.vend_serial_number_id', '=', 'vend_serial_numbers.id')
                    ->leftJoin('vend_models', 'vend_models.id', '=', 'vends.vend_model_id')
                    ->leftJoin('vend_configs', 'vend_configs.id', '=', 'vends.vend_config_id')
                    ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
                    ->when($request->id, function($query, $search) {
                        $query->where('id', $search);
                    })
                    ->when($request->code, function($query, $search) {
                        $query->where('vend_serial_numbers.code', 'LIKE', "%{$search}%");
                    })
                    ->when($request->vend_codes, function($query, $search) {
                        if(strpos($search, ',') !== false) {
                            $search = explode(',', $search);
                            $query->whereIn('vends.code', $search);
                        }else {
                            $query->where('vends.code', 'LIKE', "%{$search}%");
                        }
                    })
                    ->when($request->vendModels, function($query, $search) {
                        if(!in_array('all', $search)){
                            $query->whereIn('vends.vend_model_id', $search);
                        }
                    })
                    ->when($request->vendConfigs, function($query, $search) {
                        if(!in_array('all', $search)){
                            $query->whereIn('vends.vend_config_id', $search);
                        }
                    })
                    ->when($request->vendPrefixes, function($query, $search) {
                        if(!in_array('all', $search)){
                            $query->whereIn('vends.vend_prefix_id', $search);
                        }
                    })
                    ->when($request->status, function($query, $search) {
                        if($search != 'all') {
                            if($search === 'active') {
                                $query->where('vends.is_active', true);
                            }else if($search === 'factory') {
                                $query->where('vends.is_testing', true);
                            }else if($search === 'disposed') {
                                $query->where('vends.is_disposed', true);
                            }else {
                                $query->where('vends.is_active', false)
                                    ->where('vends.is_testing', false)
                                    ->where('vends.is_disposed', false);
                            }
                        }
                    })
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->select(
                        'vend_serial_numbers.*',
                        'vends.code as vend_code',
                        'vends.begin_date as vend_begin_date',
                        DB::raw('
                            CASE
                            WHEN vends.is_disposed = true THEN "Disposed"
                            WHEN vends.is_testing = true THEN "Factory"
                            WHEN vends.is_active = true THEN "Active"
                            ELSE "Not Active"
                            END as vend_status
                        '),
                        'vend_models.name as vend_model_name',
                        'vend_configs.name as vend_config_name',
                        'vend_prefixes.name as vend_prefix_name'
                    )
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
                ),
                'vendConfigOptions' => VendConfigResource::collection(
                    VendConfig::orderBy('name')->get()
                ),
                'vendModelOptions' => VendModelResource::collection(
                    VendModel::orderBy('name')->get()
                ),
                'vendPrefixOptions' => VendPrefixResource::collection(
                    VendPrefix::orderBy('name')->get()
                ),
        ]);
    }

    public function exportExcel(Request $request)
    {
        $request->merge([
            'sortKey' => $request->sortKey ? $request->sortKey : 'code',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        $vendSerialNumbers = VendSerialNumber::query()
            ->leftJoin('vends', 'vends.vend_serial_number_id', '=', 'vend_serial_numbers.id')
            ->leftJoin('vend_models', 'vend_models.id', '=', 'vends.vend_model_id')
            ->leftJoin('vend_configs', 'vend_configs.id', '=', 'vends.vend_config_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->when($request->id, function($query, $search) {
                $query->where('id', $search);
            })
            ->when($request->code, function($query, $search) {
                $query->where('vend_serial_numbers.code', 'LIKE', "%{$search}%");
            })
            ->when($request->vend_codes, function($query, $search) {
                if(strpos($search, ',') !== false) {
                    $search = explode(',', $search);
                    $query->whereIn('vends.code', $search);
                }else {
                    $query->where('vends.code', 'LIKE', "%{$search}%");
                }
            })
            ->when($request->vendModels, function($query, $search) {
                if(!in_array('all', $search)){
                    $query->whereIn('vends.vend_model_id', $search);
                }
            })
            ->when($request->vendConfigs, function($query, $search) {
                if(!in_array('all', $search)){
                    $query->whereIn('vends.vend_config_id', $search);
                }
            })
            ->when($request->vendPrefixes, function($query, $search) {
                if(!in_array('all', $search)){
                    $query->whereIn('vends.vend_prefix_id', $search);
                }
            })
            ->when($request->status, function($query, $search) {
                if($search != 'all') {
                    if($search === 'active') {
                        $query->where('vends.is_active', true);
                    }else if($search === 'factory') {
                        $query->where('vends.is_testing', true);
                    }else if($search === 'disposed') {
                        $query->where('vends.is_disposed', true);
                    }else {
                        $query->where('vends.is_active', false)
                            ->where('vends.is_testing', false)
                            ->where('vends.is_disposed', false);
                    }
                }
            })
            ->when($request->sortKey, function($query, $search) use ($request) {
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            })
            ->select(
                'vend_serial_numbers.*',
                'vends.code as vend_code',
                'vends.begin_date as vend_begin_date',
                DB::raw('
                    CASE
                    WHEN vends.is_disposed = true THEN "Disposed"
                    WHEN vends.is_testing = true THEN "Factory"
                    WHEN vends.is_active = true THEN "Active"
                    ELSE "Not Active"
                    END as vend_status
                '),
                'vend_models.name as vend_model_name',
                'vend_configs.name as vend_config_name',
                'vend_prefixes.name as vend_prefix_name'
            )
            ->get();

            return (new FastExcel($this->yieldOneByOne($vendSerialNumbers)))->download('VendSerialNumber'.Carbon::now()->toDateTimeString().'.xlsx', function ($vendSerialNumber) {
                return [
                    'Serial Number' => $vendSerialNumber->code,
                    'Remarks' => $vendSerialNumber->desc,
                    'Machine' => $vendSerialNumber->vend_code,
                    'Model' => $vendSerialNumber->vend_model_name,
                    'Status' => $vendSerialNumber->vend_status,
                    'Begin Date' => Carbon::parse($vendSerialNumber->vend_begin_date)->toDateString(),
                    'Setting Chart' => $vendSerialNumber->vend_config_name,
                    'Prefix' => $vendSerialNumber->vend_prefix_name,
                ];
            });
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:vend_serial_numbers',
        ]);

        $model = VendSerialNumber::create($request->all());

        return redirect()->route('vend-serial-numbers');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'code' => 'required|unique:vend_serial_numbers,code,'.$id,
        ]);

        $model = VendSerialNumber::findOrFail($id);
        $model->update($request->all());

        return redirect()->route('vend-serial-numbers');
    }

    public function delete($id)
    {
        $model = VendSerialNumber::findOrFail($id);
        $model->delete();

        return redirect()->route('vend-serial-numbers');
    }

    private function yieldOneByOne($items) {
        foreach($items as $item) {
            yield $item;
        }
    }
}
