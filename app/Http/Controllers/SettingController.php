<?php

namespace App\Http\Controllers;

use App\Http\Resources\CashlessTerminalResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\DeliveryPlatformResource;
use App\Http\Resources\KeyResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\ModemTypeResource;
use App\Http\Resources\ModemUnitResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\SellingPriceResource;
use App\Http\Resources\SimcardResource;
use App\Http\Resources\VendConfigResource;
use App\Http\Resources\VendContractResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendSerialNumberResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendDBResource;
use App\Jobs\PublishMqtt;
use App\Services\VendParameterService;
use App\Models\CashlessTerminal;
use App\Models\Category;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CategoryGroup;
use App\Models\DeliveryPlatform;
use App\Models\Key;
use App\Models\LocationType;
use App\Models\ModemType;
use App\Models\ModemUnit;
use App\Models\Operator;
use App\Models\ProductMapping;
use App\Models\SellingPrice;
use App\Models\Simcard;
use App\Models\Vend;
use App\Models\VendConfig;
use App\Models\VendContract;
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

    protected $vendParameterService;

    public function __construct()
    {
        $this->middleware(['permission:read vend-settings']);
        $this->vendParameterService = new VendParameterService();
    }

    public function index(Request $request)
    {
        $request->merge(['numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100]);
        if(!$request->operators) {
            if(auth()->user()->operator->code == 'HIPL') {
                $request->merge(['operators' => [
                    auth()->user()->operator_id,
                    Operator::where('code', 'HIMD')->first()?->id,
                    Operator::where('code', 'LEA')->first()?->id,
                    Operator::where('code', 'DCVIC')->first()?->id,
                    Operator::where('code', 'HIESG')->first()?->id,
                    Operator::where('code', 'IP')->first()?->id,
                ]]);
            }else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }
        $request->merge(['sortKey' => $request->sortKey ? $request->sortKey : 'code']);
        $request->merge(['sortBy' => $request->sortBy ? $request->sortBy : false]);
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

        $vends = Vend::query()
            ->with([
                'cashlessTerminal',
                'customer:id,code,name,is_active,person_id,person_json,virtual_customer_code,virtual_customer_prefix,operator_id,selling_price_type',
                'customer.operator:id,code,name',
                'deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform',
                'modemType',
                'modemUnit',
                'productMapping.upcomingProductMappings',
                'simcard',
                'upcomingProductMapping',
                'vendModel',
                'vendPrefix',
                'vendConfig',
                'vendSerialNumber',
            ])
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->leftJoin('keys', 'keys.id', '=', 'vends.key_id')
            ->leftJoin('operators', 'operators.id', '=', 'vends.operator_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('product_mappings as upcoming_product_mappings', 'product_mappings.id', '=', 'vends.upcoming_product_mapping_id')
            ->leftJoin('vend_configs', 'vend_configs.id', '=', 'vends.vend_config_id')
            ->leftJoin('vend_models', 'vend_models.id', '=', 'vends.vend_model_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->leftJoin('vend_serial_numbers', 'vend_serial_numbers.id', '=', 'vends.vend_serial_number_id')
            ->filterIndex($request)
            ->select(
                'customers.code AS customer_code',
                'keys.name AS key_name',
                'operators.code AS operator_code',
                'operators.name AS operator_name',
                'product_mappings.name AS product_mapping_name',
                'upcoming_product_mappings.name AS upcoming_product_mapping_name',
                'vends.id',
                'vends.acb_vmc_pa_json',
                'vends.begin_date',
                'vends.cashless_terminal_id',
                'vends.code',
                'vends.customer_id',
                'vends.apk_ver_json',
                'vends.serial_num',
                'vends.is_active',
                'vends.is_disposed',
                'vends.is_testing',
                'vends.is_using_server_price',
                'vends.label_name',
                'vends.lcd_monitor_id',
                'vends.led_matrix_panel_id',
                'vends.last_updated_at',
                'vends.modem_type_id',
                'vends.modem_unit_id',
                'vends.parameter_json',
                'vends.name',
                'vends.operator_id',
                'vends.product_mapping_id',
                'vends.termination_date',
                'vends.firmware_ver',
                'vends.private_key',
                'vends.simcard_id',
                'vends.upcoming_product_mapping_id',
                'vends.vend_config_id',
                'vends.vend_contract_id',
                'vends.vend_model_id',
                'vends.vend_prefix_id',
                'vends.vend_serial_number_id',
                'vends.vend_vend_config_version',
                'vend_configs.name AS vend_config_name',
                'vend_models.name AS vend_model_name',
                'vend_prefixes.name AS vend_prefix_name',
                'vend_serial_numbers.code AS vend_serial_number_code',
            );
        $vends = $this->filterOperator($vends);

        $vends = $vends->groupBy('vends.id');

        $vends = $vends->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
            ->withQueryString();

            // dd($request->all());
        return Inertia::render('Setting/Index', [
            'cashlessTerminalOptions' => CashlessTerminalResource::collection(
                CashlessTerminal::orderBy('code')->get()
            ),
            'cashlessMfgOptions' => Vend::query()
                ->select(DB::raw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(acb_vmc_pa_json, '$.CSHL_MFG')) AS value"))
                ->whereNotNull('acb_vmc_pa_json')
                ->whereRaw("JSON_EXTRACT(acb_vmc_pa_json, '$.CSHL_MFG') IS NOT NULL")
                ->orderBy('value')
                ->get()
                ->pluck('value')
                ->filter() // remove null/empty strings
                ->unique()
                ->values(),
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'cmsEndpoint' => env('CMS_URL'),
            'deliveryPlatformOptions' => DeliveryPlatformResource::collection(
                DeliveryPlatform::orderBy('name')->get()
            ),
            'keyOptions' => KeyResource::collection(
                Key::orderBy('name')->get()
            ),
            'lcdMonitorOptions' => Vend::LCD_MONITOR_MAPPINGS,
            'ledMatrixPanelOptions' => Vend::LED_MATRIX_PANEL_MAPPINGS,
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'modemTypeOptions' => ModemTypeResource::collection(
                ModemType::orderBy('id')->get()
            ),
            'modemUnitOptions' => ModemUnitResource::collection(
                ModemUnit::orderBy('imei')->get()
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
            'vendContractOptions' => VendContractResource::collection(
                VendContract::orderBy('name')->get()
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
        $vendInit = Vend::withoutGlobalScopes()
            ->where('id', $id)
            ->first();

        $type = $vendInit->customer?->server_price_type ?? SellingPrice::TYPE_1;

        $vend = Vend::withoutGlobalScopes()
        ->with([
            'cashlessTerminal',
            'customer',
            'customer.deliveryAddress',
            'customer.contact',
            'customerVendBindings.customer',
            'deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform',
            'key',
            'logs',
            'modemType',
            'modemUnit',
            'operator',
            'productMapping',
            'simcard',
            'upcomingProductMapping',
            'vendConfig',
            'vendChannels:id,amount,amount2,code,vend_id,product_id',
            'vendChannels.product:id,name,code,desc',
            'vendChannels.product.thumbnail',
            'vendChannels.product.sellingPrices' => function ($query) use ($type) {
                $query->where('type', $type);
            },
            'vendModel',
            'vendPrefix',
            'vendSerialNumber',
        ])
        ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
        ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
        ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
        ->leftJoin('product_mappings as upcoming_product_mappings', 'product_mappings.id', '=', 'vends.upcoming_product_mapping_id')
        ->leftJoin('addresses', function($query) {
            $query->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                    ->where('addresses.type', '=', 2)
                    ->limit(1);
        })
        ->leftJoin('vend_configs', 'vend_configs.id', '=', 'vends.vend_config_id')
        ->leftJoin('vend_models', 'vend_models.id', '=', 'vends.vend_model_id')
        ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
        ->where('vends.id', $id)
        ->select(
            'vends.id',
            'vends.code',
            'customers.id AS customer_id',
            DB::raw('CASE WHEN customers.person_id IS NOT NULL THEN CONCAT(customers.virtual_customer_code," (",customers.virtual_customer_prefix,")") ELSE customers.code END AS customer_code'),
            'customers.name AS customer_name',
            'customers.person_id',
            'customers.selling_price_type',
            'product_mappings.name AS product_mapping_name',
            'upcoming_product_mappings.name AS upcoming_product_mapping_name',
            'vends.cashless_terminal_id',
            'vends.claw_machine_board_id',
            'vends.claw_machine_body_id',
            'vends.lcd_monitor_id',
            'vends.led_matrix_panel_id',
            'vends.customer_movement_history_json',
            'vends.begin_date',
            'vends.is_disposed',
            'vends.is_using_server_price',
            'vends.simcard_id',
            'vends.termination_date',
            'vends.label_name',
            'vends.menu_frame_id',
            'vends.modem_type_id',
            'vends.modem_unit_id',
            'vends.operator_id',
            'vends.product_mapping_id',
            'vends.server_price_type',
            // 'vends.serial_num',
            'vends.key_id',
            'vends.upcoming_product_mapping_id',
            'vends.vend_config_id',
            'vends.vend_contract_id',
            'vends.vend_model_id',
            'vends.vend_prefix_id',
            'vends.vend_serial_number_id',
            'vends.vend_vend_config_version',
            'vend_configs.name AS vend_config_name',
            'vend_models.name AS vend_model_name',
            'vend_prefixes.name AS vend_prefix_name',
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
            'vend_prefix_id' => $request->vend_prefix_id ? $request->vend_prefix_id : $vend->vend_prefix_id,
            'vend_config_id' => $request->vend_config_id ? $request->vend_config_id : $vend->vend_config_id,
        ]);
        $upcomingProductMappingOptions = ProductMapping::find($vend->product_mapping_id) ? ProductMapping::find($vend->product_mapping_id)->upcomingProductMappings : [];

        return Inertia::render('Setting/Edit', [
            'cashlessTerminalOptions' => CashlessTerminalResource::collection(
                CashlessTerminal::orderBy('code')->get()
            ),
            'clawMachineBoardOptions' => Vend::CLAW_MACHINE_BOARD_MAPPINGS,
            'clawMachineBodyOptions' => Vend::CLAW_MACHINE_BODY_MAPPINGS,
            'countries' => CountryResource::collection(
                Country::query()
                    ->orderBy('sequence')
                    ->orderBy('name')
                    ->get()
                ),
            'keyOptions' => KeyResource::collection(
                Key::orderBy('name')->get()
            ),
            'lcdMonitorOptions' => Vend::LCD_MONITOR_MAPPINGS,
            'ledMatrixPanelOptions' => Vend::LED_MATRIX_PANEL_MAPPINGS,
            'modemTypeOptions' => ModemTypeResource::collection(
                ModemType::orderBy('id')->get()
            ),
            'modemUnitOptions' => ModemUnitResource::collection(
                ModemUnit::query()
                    ->where(function($query) use ($vend) {
                        $query->doesntHave('vend')
                              ->orWhereHas('vend', function($q) use ($vend) {
                                  $q->where('vends.id', $vend->id);
                              });
                    })
                    ->orderBy('imei')
                    ->get()
            ),
            'menuFrameOptions' => Vend::MENU_FRAME_MAPPINGS,
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productMappingOptions' => ProductMappingResource::collection(
                ProductMapping::query()
                    // ->when($request->vend_prefix_id, function($query) use ($request) {
                        ->whereHas('vendPrefixes', function($query) use ($request) {
                            $query->where('vend_prefixes.id', $request->vend_prefix_id);
                        })
                    // })
                    ->orderBy('name')
                    ->get()
            ),
            'sellingPriceTypeOptions' => SellingPrice::TYPE_MAPPINGS,
            'simcardOptions' => SimcardResource::collection(
                Simcard::orderBy('code')->get()
            ),
            'adminCustomerOptions' => CustomerResource::collection(
                $customers
            ),
            'upcomingProductMappingOptions' => ProductMappingResource::collection(
                $upcomingProductMappingOptions
            ),
            'vend' => $vend,
            'vendConfigOptions' => VendConfigResource::collection(
                VendConfig::orderBy('name')->get()
            ),
            'vendContractOptions' => VendContractResource::collection(
                VendContract::orderBy('name')->get()
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
                    ->whereDoesntHave('vend', function($query) use ($vend) {
                        $query->where('vends.id', '!=', $vend->id);
                    })
                    ->orderBy('code')
                    ->get()
            ),
            'versionOptions' => VendConfig::VERSION,
            'type' => 'update',
        ]);
    }

    public function parameter(Request $request, $id)
    {
        $vend = Vend::withoutGlobalScopes()
        ->with([
            'operator',
        ])
        ->where('vends.id', $id)
        ->select(
            'vends.id',
            'vends.code',
            'vends.settings_parameter_json'
        )
        ->first();

        return Inertia::render('Setting/Parameter', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vend' => VendResource::make($vend),
            'type' => 'update',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required',
        ]);

        $vend = Vend::where('code', $request->code)->first();

        if($vend) {
            return redirect()->route('settings.edit', [$vend->id])->with('errors', 'Vend Code already exists.');
        }

        // dd($request->all());

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

    public function updateParameter(Request $request, $vendID)
    {
        $vend = Vend::findOrFail($vendID);
        $parameters = $this->vendParameterService->getCampaignParameter($request->all());

        $vend->settings_parameter_json = $parameters;
        $vend->save();

        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => 'TYPESYNCSETTINGSPARAM',
            'time' => Carbon::now()->timestamp,
            'action' => '',
            'mid' => $vend->code,
        ]));
        $contentLength = strlen($content);
        $key = $vend && $vend->private_key ? $vend->private_key : '123456789110138A';
        $md5 = md5($fid.','.$contentLength.','.$content.$key);

        PublishMqtt::dispatch('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5)->onQueue('high');
    }
}
