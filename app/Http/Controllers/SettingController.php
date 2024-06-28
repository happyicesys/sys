<?php

namespace App\Http\Controllers;

use App\Http\Resources\CashlessTerminalResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\KeyResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\SellingPriceResource;
use App\Http\Resources\SimcardResource;
use App\Http\Resources\VendConfigResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendSerialNumberResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendDBResource;
use App\Models\CashlessTerminal;
use App\Models\Category;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CategoryGroup;
use App\Models\Key;
use App\Models\LocationType;
use App\Models\Operator;
use App\Models\ProductMapping;
use App\Models\SellingPrice;
use App\Models\Simcard;
use App\Models\Vend;
use App\Models\VendConfig;
use App\Models\VendModel;
use App\Models\VendPrefix;
use App\Models\VendSerialNumber;
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
        $request->merge(['operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id]);
        $request->merge(['sortKey' => $request->sortKey ? $request->sortKey : 'code']);
        $request->merge(['sortBy' => $request->sortBy ? $request->sortBy : true]);
        $className = get_class(new Customer());
        if(!isset($request->status)) {
            if(
                auth()->user()->hasRole('superadmin') or
                auth()->user()->hasRole('admin') or
                auth()->user()->hasRole('supervisor') or
                auth()->user()->hasRole('driver')
            ) {
                $request->merge([
                    'status' => 'active',
                ]);

            }else {
                $request->merge([
                    'status' => 'all'
                ]);
            }
        }
        // dd($request->all());

        $vends = Vend::query()
            ->with([
                'cashlessTerminal',
                'customer:id,code,name,is_active,person_id,person_json,virtual_customer_code,virtual_customer_prefix,operator_id,selling_price_type',
                'customer.operator:id,code,name',
                'productMapping',
                'simcard',
                'vendModel',
                'vendPrefix',
                'vendConfig',
                'vendSerialNumber',
            ])
            ->leftJoin('operators', 'operators.id', '=', 'vends.operator_id')
            ->filterIndex($request)
            ->select(
                'operators.code AS operator_code',
                'operators.name AS operator_name',
                'vends.id',
                'vends.acb_vmc_pa_json',
                'vends.begin_date',
                'vends.cashless_terminal_id',
                'vends.code',
                'vends.customer_id',
                'vends.apk_ver_json',
                'vends.serial_num',
                'vends.is_active',
                'vends.is_testing',
                'vends.last_updated_at',
                'vends.parameter_json',
                'vends.name',
                'vends.operator_id',
                'vends.product_mapping_id',
                'vends.termination_date',
                'vends.firmware_ver',
                'vends.last_updated_at',
                'vends.private_key',
                'vends.simcard_id',
                'vends.vend_config_id',
                'vends.vend_model_id',
                'vends.vend_prefix_id',
                'vends.vend_serial_number_id',
            );
        $vends = $this->filterOperator($vends);

        $vends = $vends->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
            ->withQueryString();

            // dd($request->all());
        return Inertia::render('Setting/Index', [
            'cashlessTerminalOptions' => CashlessTerminalResource::collection(
                CashlessTerminal::orderBy('code')->get()
            ),
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'cmsEndpoint' => env('CMS_URL'),
            'keyOptions' => KeyResource::collection(
                Key::orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'sellingPriceTypeOptions' => SellingPrice::TYPE_MAPPINGS,
            'simcardOptions' => SimcardResource::collection(
                Simcard::orderBy('code')->get()
            ),
            'vends' => VendResource::collection(
                $vends
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

    public function create()
    {
        $vend = new Vend();

        return Inertia::render('Setting/Create', [
            'vend' => $vend,
            'type' => 'create',
        ]);
    }


    public function edit(Request $request, $id)
    {
        $vend = Vend::withoutGlobalScopes()
        ->with([
            'cashlessTerminal',
            'customer',
            'customer.deliveryAddress',
            'customer.contact',
            'key',
            'logs',
            'operator',
            'productMapping',
            'simcard',
            'vendConfig',
            'vendModel',
            'vendPrefix',
            'vendSerialNumber',
        ])
        ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
        ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
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
            'vends.cashless_terminal_id',
            'vends.customer_movement_history_json',
            'vends.begin_date',
            'vends.simcard_id',
            'vends.termination_date',
            'vends.operator_id',
            'vends.product_mapping_id',
            // 'vends.serial_num',
            'vends.key_id',
            'vends.vend_config_id',
            'vends.vend_model_id',
            'vends.vend_prefix_id',
            'vends.vend_serial_number_id',
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

        $request->merge([
            'vend_prefix_id' => $request->vend_prefix_id ? $request->vend_id_prefix_id : $vend->vend_prefix_id,
            'vend_config_id' => $request->vend_config_id ? $request->vend_config_id : $vend->vend_config_id,
        ]);

        return Inertia::render('Setting/Edit', [
            'cashlessTerminalOptions' => CashlessTerminalResource::collection(
                CashlessTerminal::orderBy('code')->get()
            ),
            'countries' => CountryResource::collection(
                Country::query()
                    ->orderBy('sequence')
                    ->orderBy('name')
                    ->get()
                ),
            'keyOptions' => KeyResource::collection(
                Key::orderBy('name')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productMappingOptions' => ProductMappingResource::collection(
                ProductMapping::query()
                    ->when($request->vend_prefix_id, function($query) use ($request) {
                        $query->whereHas('vendPrefixes', function($query) use ($request) {
                            $query->where('vend_prefixes.id', $request->vend_prefix_id);
                        });
                    })
                    ->orderBy('name')
                    ->get()
            ),
            'simcardOptions' => SimcardResource::collection(
                Simcard::orderBy('code')->get()
            ),
            'adminCustomerOptions' => CustomerResource::collection(
                $customers
            ),
            'vend' => $vend,
            'vendConfigOptions' => VendConfigResource::collection(
                VendConfig::orderBy('name')->get()
            ),
            'vendModelOptions' => VendModelResource::collection(
                VendModel::orderBy('name')->get()
            ),
            'vendPrefixOptions' =>
                VendPrefixResource::collection(
                    VendPrefix::query()
                        ->when($request->vend_config_id, function($query) use ($request) {
                            $query->whereHas('vendConfigs', function($query) use ($request) {
                                $query->where('vend_configs.id', $request->vend_config_id);
                            });
                        })
                        ->orderBy('name')
                        ->get()
            ),
            'vendSerialNumberOptions' => VendSerialNumberResource::collection(
                VendSerialNumber::query()
                    ->orderBy('code')
                    ->get()
            ),
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
        $vend->operator_id = auth()->user()->operator_id;
        $vend->save();

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
