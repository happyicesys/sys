<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendDBResource;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CategoryGroup;
use App\Models\LocationType;
use App\Models\Operator;
use App\Models\Vend;
use App\Traits\HasFilter;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class SettingController extends Controller
{
    use HasFilter;

    public function __construct()
    {
        $this->middleware(['permission:admin-access vends']);
    }

    public function index(Request $request)
    {
        $request->merge(['numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100]);
        $request->merge(['sortKey' => $request->sortKey ? $request->sortKey : 'code']);
        $request->merge(['sortBy' => $request->sortBy ? $request->sortBy : true]);
        $className = get_class(new Customer());
        if(!isset($request->is_active)) {
            if(
                auth()->user()->hasRole('superadmin') or
                auth()->user()->hasRole('admin') or
                auth()->user()->hasRole('supervisor') or
                auth()->user()->hasRole('driver')
            ) {
                $request->merge(['is_active' => 'true']);
            }else {
                $request->merge(['is_active' => 'all']);
            }
        }

        $vends = Vend::query()
            ->with([
                'latestOperator:id,code,name',
                'latestVendBinding.customer:id,code,name,is_active,person_id,customer_json,virtual_customer_code,virtual_customer_prefix',
            ])
            ->filterIndex($request)
            ->select(
                'id',
                'vends.id',
                'vends.begin_date',
                'vends.code',
                'vends.apk_ver_json',
                'vends.serial_num',
                'vends.is_active',
                'vends.last_updated_at',
                'vends.name',
                'vends.termination_date',
                'vends.firmware_ver',
                'vends.last_updated_at',
                'vends.private_key'
            );

        $vends = $vends->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
            ->withQueryString();

            // dd($request->all());
        return Inertia::render('Setting/Index', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vends' => VendResource::collection(
                $vends
            ),
        ]);
    }

    public function editOrCreate($id = null, $type)
    {
        if($id) {
            $vend = Vend::query()
                ->leftJoin('vend_bindings', function($query) {
                    $query->on('vend_bindings.vend_id', '=', 'vends.id')
                            ->where('vend_bindings.is_active', true)
                            ->latest('vend_bindings.begin_date')
                            ->limit(1);
                })
                ->leftJoin('customers', 'customers.id', '=', 'vend_bindings.customer_id')
                ->leftJoin('operator_vend', function($query) {
                    $query->on('operator_vend.vend_id', '=', 'vends.id')
                            ->latest('operator_vend.begin_date')
                            ->limit(1);
                })
                ->select(
                    '*',
                    'vends.id',
                    'vends.begin_date',
                    'vends.code',
                    'vends.name',
                    'vends.termination_date',
                    'customers.id AS customer_id',
                    'customers.code AS customer_code',
                    'customers.name AS customer_name',
                    'vends.is_active'
                    )
                ->where('vends.id', $id)
                ->first();
        }else {
            $vend = new Vend();
        }

        $response = Http::get(env('CMS_URL') . '/api/vends/unbind');


        return Inertia::render('Setting/Edit', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'adminCustomerOptions' => $response->collect(),
            'vend' => $vend,
            'type' => $type,
        ]);
    }

    public function toggleActivation($vendId)
    {
        $vend = Vend::findOrFail($vendId);

        if($vend->is_active) {
            $vend->update([
                'is_active' => false,
                'termination_date' => Carbon::now(),
            ]);
            if($vend->latestVendBinding()->exists()) {
                $vend->latestVendBinding->update([
                    'is_active' => false,
                    'termination_date' => Carbon::now(),
                ]);
            }
        }else {
            $vend->update([
                'is_active' => true,
                'termination_date' => null,
            ]);
            // if($vend->firstVendBinding()->exists()) {
            //     $vend->firstVendBinding->update([
            //         'is_active' => true,
            //         'termination_date' => null,
            //     ]);
            // }
        }

        return redirect()->route('settings.edit', [$vendId, 'update']);
    }
}
