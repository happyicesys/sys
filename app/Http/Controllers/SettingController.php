<?php

namespace App\Http\Controllers;

use App\Http\Resources\CountryResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendDBResource;
use App\Models\Category;
use App\Models\Country;
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
                'customer:id,code,name,is_active,person_id,person_json,virtual_customer_code,virtual_customer_prefix,operator_id',
                'customer.operator:id,code,name',
            ])
            ->filterIndex($request)
            ->select(
                'id',
                'vends.id',
                'vends.begin_date',
                'vends.code',
                'vends.customer_id',
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

    public function create()
    {
        $vend = new Vend();

        return Inertia::render('Setting/Create', [
            'vend' => $vend,
            'type' => 'create',
        ]);
    }


    public function edit($id)
    {
        $vend = Vend::query()
        ->with([
            'customer',
            'customer.deliveryAddress',
            'customer.contact',
        ])
        ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
        ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
        ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
        ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
        ->leftJoin('addresses', function($query) {
            $query->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                    ->where('addresses.type', '=', 2)
                    ->limit(1);
        })
        ->where('vends.id', $id)
        ->select(
            'vends.id',
            'vends.code',
            'customers.id AS customer_id',
            DB::raw('CASE WHEN customers.person_id IS NOT NULL THEN CONCAT(customers.virtual_customer_code," (",customers.virtual_customer_prefix,")") ELSE customers.code END AS customer_code'),
            'customers.name AS customer_name',
            'customers.person_id',
            'vends.begin_date',
            'vends.termination_date',
            DB::raw('CASE WHEN vends.is_testing THEN true ELSE false END AS is_testing'),
            DB::raw('CASE WHEN vends.is_active THEN true ELSE false END AS is_active'),
        )
        ->first();

        $customers = Customer::query()
            ->select(
                'id',
                'code',
                'name',
                'is_active',
                'person_id',
                'person_json',
                'virtual_customer_code',
                'virtual_customer_prefix',
                'operator_id'
            )
            ->doesntHave('vend')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Setting/Edit', [
            'countries' => CountryResource::collection(
                Country::query()
                    ->orderBy('sequence')
                    ->orderBy('name')
                    ->get()
                ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'adminCustomerOptions' => CustomerResource::collection(
                $customers
            ),
            'vend' => $vend,
            'type' => 'update',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required',
        ]);

        if(Vend::where('code', $request->code)->exists()) {
            return redirect()->back()->with('errors', 'Vend ID already exists');
        }

        $vend = Vend::create($request->all());

        return redirect()->route('settings.edit', [$vend->id]);
    }

    public function toggleActivation($vendId)
    {
        $vend = Vend::findOrFail($vendId);

        if($vend->is_active) {
            $vend->update([
                'is_active' => false,
                'termination_date' => Carbon::now(),
            ]);
            if($vend->customer()->exists()) {
                $vend->customer->update([
                    'is_active' => false,
                    'termination_date' => Carbon::now(),
                ]);
            }
        }else {
            $vend->update([
                'is_active' => true,
                'termination_date' => null,
            ]);
            if($vend->customer()->exists()) {
                $vend->customer->update([
                    'is_active' => true,
                    'termination_date' => null,
                ]);
            }
        }

        return redirect()->route('settings.edit', [$vendId]);
    }
}
