<?php

namespace App\Http\Controllers;

use App\Http\Resources\CardTerminalResource;
use App\Http\Resources\CardTerminalUnitResource;
use App\Http\Resources\CashlessTerminalResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\DeliveryPlatformResource;
use App\Http\Resources\KeyResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\ModemTypeResource;
use App\Http\Resources\ModemUnitResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\SimcardResource;
use App\Http\Resources\VendConfigResource;
use App\Http\Resources\VendContractResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendSerialNumberResource;
use App\Http\Resources\VendStickerResource;
use App\Jobs\PublishMqtt;
use App\Models\CardTerminal;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\CashlessTerminal;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
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
use App\Models\VendSticker;
use App\Services\CardSettlement\CardTerminalBindingService;
use App\Services\VendParameterService;
use App\Traits\HasFilter;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingController extends Controller
{
    use HasFilter;

    protected $vendParameterService;

    public function __construct()
    {
        $this->middleware(['permission:read machine-settings']);
        $this->vendParameterService = new VendParameterService;
    }

    public function index(Request $request)
    {
        $request->merge(['numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100]);
        if (! $request->operators) {
            if (auth()->user()->operator->code == 'HIPL') {
                $request->merge([
                    'operators' => \App\Support\OperatorScope::defaultFilterIds(),
                ]);
            } else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }
        $request->merge(['sortKey' => $request->sortKey ? $request->sortKey : 'code']);
        $request->merge(['sortBy' => $request->sortBy ? $request->sortBy : false]);
        $className = get_class(new Customer);
        if (! isset($request->status)) {
            if (
                auth()->user()->hasRole('superadmin') or
                auth()->user()->hasRole('admin') or
                auth()->user()->hasRole('supervisor') or
                auth()->user()->hasRole('observer_transactions') or
                auth()->user()->isDriver()
            ) {
                $request->merge([
                    'status' => 'active',
                ]);

            } else {
                $request->merge([
                    'status' => 'all',
                ]);
            }
        }

        $vends = Vend::query()
            ->with([
                'cardTerminal',
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
            ->leftJoin('addresses', function ($query) {
                $query->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                    ->where('addresses.type', '=', 2);
            })
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
                'vends.card_terminal_id',
                'vends.cashless_terminal_id',
                'vends.code',
                'vends.customer_id',
                'vends.apk_ver_json',
                'vends.serial_num',
                'vends.is_active',
                'vends.is_disposed',
                'vends.is_sold',
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
                'addresses.postcode AS postcode',
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
            // Card terminal types (Nayax / Nets / Nets-Auresys / PAX / MLS). Sourced from
            // the user-defined `card_terminals` table (formerly read live from
            // vends.acb_vmc_pa_json->CSHL_MFG, which was unreliable).
            //
            // Returned as { name => name } so the Vue side's
            // `Object.entries(...)` produces { id: name, value: name } options
            // and the filter posts back a name (e.g. "CAS") rather than an
            // array index. The corresponding Vend::scopeFilterIndex filter
            // matches on card_terminals.name.
            'cashlessMfgOptions' => CardTerminal::orderBy('name')->pluck('name', 'name')->all(),
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
        $vend = new Vend;

        return Inertia::render('Setting/Create', [
            'vend' => $vend,
            'type' => 'create',
            // Machine Type is chosen at creation only (read-only on Setting/Edit).
            'machineTypeOptions' => Vend::MACHINE_TYPE_MAPPINGS,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $vendInit = Vend::withoutGlobalScopes()
            ->where('id', $id)
            ->first();

        // The Site's RP drives the channel price list (the vend carries no tier of its own).
        $type = $vendInit->customer?->selling_price_type ?? SellingPrice::TYPE_1;

        $vend = Vend::withoutGlobalScopes()
            ->with([
                'cardTerminal',
                'cashlessTerminal',
                'customer',
                'customer.deliveryAddress',
                'customer.contact',
                'customerVendBindings.customer',
                // Who bound/unbound the site — rendered next to the
                // timestamp in the "Site Binding History" list.
                'customerVendBindings.user:id,name',
                'deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform',
                'key',
                'logs',
                'modemType',
                'modemUnit',
                'operator',
                'productMapping',
                'simcard',
                'latestSyncApkSettingJob',
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
                'stickers',
            ])
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('product_mappings as upcoming_product_mappings', 'product_mappings.id', '=', 'vends.upcoming_product_mapping_id')
            ->leftJoin('addresses', function ($query) {
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
                DB::raw('CASE WHEN customers.person_id IS NOT NULL THEN CONCAT(IFNULL(customers.virtual_customer_code, \'\')," (",IFNULL(customers.virtual_customer_prefix, \'\'),")") ELSE customers.code END AS customer_code'),
                'customers.name AS customer_name',
                'customers.person_id',
                'customers.selling_price_type',
                'product_mappings.name AS product_mapping_name',
                'upcoming_product_mappings.name AS upcoming_product_mapping_name',
                'vends.card_terminal_id',
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
                'vends.machine_type',
                // CityBox link — written by the Create flow (DeviceProvisioningService),
                // shown read-only here. Missing from this list until 2026-08-20: the
                // field rendered empty and every Save posted null → wiped the serial.
                'vends.citybox_equipment_id',
                'vends.citybox_synced_at',
                'vends.citybox_status_json',
                'vends.is_online',
                // 'vends.serial_num',
                'vends.key_id',
                'vends.upcoming_product_mapping_id',
                'vends.vend_config_id',
                'vends.vend_contract_id',
                'vends.vend_model_id',
                'vends.vend_prefix_id',
                'vends.vend_serial_number_id',
                'vends.vend_vend_config_version',
                // Last APK versionCode the machine reported via OTA check-in — the
                // Setting/Edit "View Screen" gate (SCREENSHOT_MIN_APK_VERSION) reads
                // it; without it in this select the button was always disabled.
                'vends.apk_version_code',
                'vend_configs.name AS vend_config_name',
                'vend_models.name AS vend_model_name',
                'vend_prefixes.name AS vend_prefix_name',
                DB::raw('CASE WHEN vends.is_testing THEN true ELSE false END AS is_testing'),
                DB::raw('CASE WHEN vends.is_active THEN true ELSE false END AS is_active'),
                DB::raw('CASE WHEN vends.is_sold THEN true ELSE false END AS is_sold'),
                DB::raw('CASE WHEN vends.is_disposed THEN true ELSE false END AS is_disposed'),
                'vends.is_enable_grab_collection',
                'vends.has_display_screen',
                'vends.is_enable_soft_keyboard_qr_pay',
                'vends.is_enable_soft_keyboard_cash_pay',
                'vends.is_enable_soft_keyboard_credit_card_pay',
                'vends.is_enable_soft_keyboard_hid_pay',
                'vends.is_fan_enabled',
            )
            ->first();

        // Only a machine with no Site can bind one, and this list is every unbound Site (1.4 MB).
        // A bound machine renders the Site as read-only text, so it never needs the picker.
        $customers = $vendInit && $vendInit->customer_id ? collect() : Customer::query()
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
        // Upcoming Product Mapping is now user-selectable on the edit form, so its
        // dropdown mirrors the full "current" mapping list (all active mappings for
        // this operator + global, plus the vend's own current/upcoming so they always
        // appear regardless of active status). Previously this list held only the
        // current mapping's derived upcoming + N/A, which made a real dropdown empty.
        $upcomingProductMappingOptions = ProductMapping::withoutGlobalScopes()
            ->with(['upcomingProductMapping'])
            ->where(function ($query) use ($vend) {
                $query->where(function ($normalQ) {
                    $normalQ->where(function ($opQ) {
                        $opQ->where('operator_id', auth()->user()->operator_id)
                            ->orWhereNull('operator_id');
                    });
                    $normalQ->where('is_active', 1);
                });
                if ($vend && $vend->product_mapping_id) {
                    $query->orWhere('id', $vend->product_mapping_id);
                }
                if ($vend && $vend->upcoming_product_mapping_id) {
                    $query->orWhere('id', $vend->upcoming_product_mapping_id);
                }
            })
            ->orderByRaw("CASE WHEN name = 'N/A' AND operator_id IS NULL THEN 1 ELSE 0 END ASC")
            ->orderBy('name')
            ->get();

        $selectedProductMapping = null;
        if ($request->has('product_mapping_id')) {
            $requestedProductMappingId = $request->product_mapping_id;
            if ($requestedProductMappingId === '' || $requestedProductMappingId === null) {
                $requestedProductMappingId = null;
            }
            $selectedProductMappingId = $requestedProductMappingId ?: $vend->product_mapping_id;

            if ($selectedProductMappingId) {
                $selectedProductMapping = ProductMapping::query()
                    ->with([
                        'productMappingItems' => function ($query) {
                            $query->orderByRaw('CASE WHEN sequence IS NULL THEN 1 ELSE 0 END')
                                ->orderBy('sequence')
                                ->orderBy('channel_code');
                        },
                        'productMappingItems.product' => function ($query) use ($type) {
                            $query->with([
                                'thumbnail',
                                'sellingPrices' => function ($query) use ($type) {
                                    $query->where('type', $type);
                                },
                            ]);
                        },
                        'productMappingItems.sellingPrice',
                    ])
                    ->find($selectedProductMappingId);
            }
        }

        // NOTE: every option list below is loaded for every machine kind, chillers
        // included. Edit.vue hides the vending-machine pickers for a chiller but
        // still resolves the vend's stored ids against these lists (and posts them
        // back), so an empty list would null hidden columns on save — and crash on
        // vend_config_version for a chiller that still carries a config chart.
        return Inertia::render('Setting/Edit', [
            // CityBox-side status layer (their ops status / heartbeat / online),
            // read from the last poll on the row — no API call. Null for other kinds.
            'chillerStatus' => $vend->chillerStatus()?->toArray(),
            // Card Terminal COMPANY (Nayax / Nets / Nets-Auresys / PAX / MLS / HID)
            // — populates the "Card Terminal Company" dropdown on the vend edit form.
            // Renamed from "Card Terminal" 2026-09-05, when the terminal ITSELF
            // became its own field below; the vends column stays card_terminal_id.
            'cardTerminalOptions' => CardTerminalResource::collection(
                CardTerminal::orderBy('name')->get()
            ),
            // The physical terminals (Data Management → Card Terminal). This page
            // is the ONLY place a terminal is put on a machine — the standalone
            // Card Terminal Bindings page was removed 2026-09-05.
            'cardTerminalUnitOptions' => CardTerminalUnitResource::collection(
                CardTerminalUnit::with('company')->orderBy('terminal_id')->get()
            ),
            // Current binding for this machine, so the form opens on what is
            // actually fitted rather than on an empty picker.
            'cardTerminalBinding' => (function () use ($vend) {
                $binding = app(CardTerminalBindingService::class)->currentBindingFor($vend);

                // The last three terminals this machine carried, newest first —
                // the hover behind the "bound … by …" line. bound_at is when the
                // row was RECORDED; bound_from is the date it covers, and the two
                // differ whenever a binding is back-dated.
                $history = CardTerminalBinding::query()
                    ->where('vend_id', $vend->id)
                    ->with('creator:id,name')
                    ->orderByDesc('id')
                    ->limit(3)
                    ->get()
                    ->map(fn (CardTerminalBinding $row) => [
                        'terminal_id' => $row->terminal_id,
                        'bound_from' => $row->bound_from?->format('Y-m-d'),
                        'bound_until' => $row->bound_until?->format('Y-m-d'),
                        'bound_at' => $row->created_at?->toIso8601String(),
                        'bound_by' => $row->boundByLabel(),
                    ]);

                return [
                    'card_terminal_unit_id' => $binding
                        ? CardTerminalUnit::where('terminal_id', $binding->terminal_id)->value('id')
                        : null,
                    'bound_from' => $binding?->bound_from?->format('Y-m-d'),
                    'bound_at' => $binding?->created_at?->toIso8601String(),
                    'bound_by' => $binding?->boundByLabel(),
                    'history' => $history,
                ];
            })(),
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
                    ->where(function ($query) use ($vend) {
                        $query->doesntHave('vend')
                            ->orWhereHas('vend', function ($q) use ($vend) {
                                $q->where('vends.id', $vend->id);
                            });
                    })
                    ->orderBy('imei')
                    ->get()
            ),
            'machineTypeOptions' => Vend::MACHINE_TYPE_MAPPINGS,
            'menuFrameOptions' => Vend::MENU_FRAME_MAPPINGS,
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productMappingOptions' => ProductMappingResource::collection(
                ProductMapping::withoutGlobalScopes()
                    // Options carry NO items (2026-09-16). Shipping every active mapping's items,
                    // products and thumbnails made this one prop 9.8 MB of a 12 MB page — a 6.5 s
                    // load on every machine. The dropdown only reads id / name / machine_type /
                    // basket_layout_json; the channel table and the freezer planogram read the
                    // vend's own live channels, and a mapping the user PREVIEWS is fetched on
                    // demand as `selectedProductMapping` (with items and the Site-tier prices)
                    // by fetchProductMappingPreviewById.
                    ->with(['upcomingProductMapping'])
                    ->where(function ($query) use ($vend) {
                        // Normal selectable options: match operator + active.
                        // DEPRECATED (2026-07): the prefix→mapping gate was removed —
                        // ALL active mappings (own operator + global) are selectable,
                        // ordered by name; the vend prefix no longer restricts this list.
                        $query->where(function ($normalQ) {
                            $normalQ->where(function ($opQ) {
                                $opQ->where('operator_id', auth()->user()->operator_id)
                                    ->orWhereNull('operator_id');
                            });
                            $normalQ->where('is_active', 1);
                        });
                        // Always include the vend's currently assigned mappings regardless of
                        // operator, prefix, or active status — so they always appear in the dropdown.
                        if ($vend && $vend->product_mapping_id) {
                            $query->orWhere('id', $vend->product_mapping_id);
                        }
                        if ($vend && $vend->upcoming_product_mapping_id) {
                            $query->orWhere('id', $vend->upcoming_product_mapping_id);
                        }
                    })
                    ->orderByRaw("CASE WHEN name = 'N/A' AND operator_id IS NULL THEN 1 ELSE 0 END ASC")
                    ->orderBy('name')
                    ->get()
            ),
            'simcardOptions' => SimcardResource::collection(
                Simcard::with('telco')->orderBy('code')->get()
            ),
            'adminCustomerOptions' => CustomerResource::collection(
                $customers
            ),
            'upcomingProductMappingOptions' => ProductMappingResource::collection(
                $upcomingProductMappingOptions
            ),
            'vend' => $vend,
            'stickerOptions' => VendStickerResource::collection(
                VendSticker::orderBy('name')->get()
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
                VendPrefix::query()
                    ->where(function ($query) use ($request) {
                        $query->when($request->vend_config_id, function ($query) use ($request) {
                            $query->whereHas('vendConfigs', function ($query) use ($request) {
                                $query->where('vend_configs.id', $request->vend_config_id);
                            });
                        });
                        $query->orWhere('vend_prefixes.name', 'N/A');
                    })
                    ->orderBy('name')
                    ->get()
            ),
            'vendSerialNumberOptions' => VendSerialNumberResource::collection(
                VendSerialNumber::query()
                    ->whereDoesntHave('vend', function ($query) use ($vend) {
                        $query->where('vends.id', '!=', $vend->id);
                    })
                    ->orderBy('code')
                    ->get()
            ),
            'selectedProductMapping' => $selectedProductMapping
                ? ProductMappingResource::make($selectedProductMapping)
                : null,
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
        $validated = $request->validate([
            'code' => 'required|integer|min:1',
            // A Smart Chiller is only born through the CityBox branch (POST /citybox/vends),
            // which links the device, forces the CB operator and binds a site. Created here it
            // would be a chiller with no CityBox link that nothing ever syncs.
            'machine_type' => [
                'nullable',
                Rule::in([Vend::MACHINE_TYPE_VENDING_MACHINE, Vend::MACHINE_TYPE_SMART_FREEZER]),
            ],
            'begin_date' => 'nullable|date',
        ], [
            'machine_type.in' => 'Choose Vending Machine or Smart Freezer. Smart Chillers are created from a CityBox device.',
        ]);

        // The code is the machine's identity fleet-wide (MQTT topic, APK machine ID) but
        // vends.code has no unique index, so check across every operator: the viewer's
        // operator scope would hide another operator's vend and let a duplicate through.
        // Prefixed machines count too: a new vending 6003 next to CityBox C6003 would make
        // every terminal lookup by bare number ambiguous, so the number stays taken.
        $existing = Vend::withoutGlobalScopes()->where('code', $validated['code'])->first(['id', 'code', 'code_prefix']);

        if ($existing) {
            return redirect()->back()->withErrors([
                'code' => $existing->code_prefix
                    ? "Machine ID {$validated['code']} is taken by {$existing->codeLabel()}."
                    : (Vend::whereKey($existing->id)->exists()
                        ? "Machine ID {$validated['code']} already exists."
                        : "Machine ID {$validated['code']} is already used by another operator."),
            ]);
        }

        // Only the create form's own fields reach the model. This used to mass-assign
        // $request->all(), and the page posts its whole form, so a customer_id left over
        // from the CityBox branch (or any fillable column an API caller sent: customer_id,
        // product mappings, citybox_equipment_id) was written with no binding history.
        // Site, mappings and hardware are set on Setting/Edit, which guards each of them.
        $vend = new Vend([
            'code' => (int) $validated['code'],
            // NOT NULL column: an explicit null would 500 on insert rather than take the default.
            'machine_type' => $validated['machine_type'] ?? Vend::MACHINE_TYPE_VENDING_MACHINE,
            'begin_date' => $validated['begin_date'] ?? null,
        ]);
        $vend->operator_id = auth()->user()->operator_id;
        $vend->save();

        return redirect()->route('settings.edit', [$vend->id]);
    }

    public function toggleActivation($vendId)
    {
        $vend = Vend::findOrFail($vendId);

        if ($vend->is_active) {
            $vend->update([
                'is_active' => false,
                'termination_date' => Carbon::now(),
            ]);
            if ($vend->customer()->exists()) {
                $vend->customer->update([
                    'is_active' => false,
                    'termination_date' => Carbon::now(),
                ]);
            }
        } else {
            $vend->update([
                'is_active' => true,
                'termination_date' => null,
            ]);
            if ($vend->customer()->exists()) {
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
        // merge, NOT getCampaignParameter: this is an update to an existing row
        // and the form does not post all 42 keys. See mergeCampaignParameter().
        $parameters = $this->vendParameterService->mergeCampaignParameter(
            $vend->settings_parameter_json,
            $request->all()
        );

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
