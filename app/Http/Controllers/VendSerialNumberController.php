<?php

namespace App\Http\Controllers;

use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\VendConfigResource;
use App\Http\Resources\VendContractResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendSerialNumberResource;
use App\Http\Resources\VendStickerResource;
use App\Http\Resources\VendResource;
use App\Models\LocationType;
use App\Models\Operator;
use App\Models\VendConfig;
use App\Models\VendContract;
use App\Models\VendPrefix;
use App\Models\VendSticker;
use App\Models\VendSerialNumber;
use App\Models\Vend;
use App\Models\VendModel;
use App\Traits\ExportOptimizationTrait;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;

class VendSerialNumberController extends Controller
{
    use ExportOptimizationTrait;
    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'code',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        return Inertia::render('VendSerialNumber/Index', [
            'lcdMonitorOptions' => Vend::LCD_MONITOR_MAPPINGS,
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendSerialNumbers' => VendSerialNumberResource::collection(
                $this->getMainQuery()->filterIndex($request)->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)->withQueryString()
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
            'stickerOptions' => VendStickerResource::collection(
                VendSticker::orderBy('name')->get()
            ),
            'permissions' => [
                'create' => auth()->user()->can('create serial-numbers'),
                'update' => auth()->user()->can('update serial-numbers'),
                'export' => auth()->user()->can('export serial-numbers'),
            ],
        ]);
    }

    public function exportExcel(Request $request)
    {
        $request->merge([
            'sortKey' => $request->sortKey ? $request->sortKey : 'code',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        $query = $this->getMainQuery()->filterIndex($request);

        // Use cursor for memory-efficient iteration
        return (new FastExcel($this->exportWithCursor($query)))->download(
            $this->formatExportFilename('VendSerialNumber', 'xlsx'),
            function ($vendSerialNumber) {
                return [
                    'Serial Number' => $vendSerialNumber->code,
                    'Remarks' => $vendSerialNumber->desc,
                    'Machine ID' => $vendSerialNumber->vend_code,
                    'Machine Model' => $vendSerialNumber->vend_model_name,
                    'LCD Monitor' => $vendSerialNumber->vend_lcd_monitor,
                    'Status' => $vendSerialNumber->vend_status,
                    'Begin Date' => Carbon::parse($vendSerialNumber->vend_begin_date)->toDateString(),
                    'Prefix' => $vendSerialNumber->vend_prefix_name,
                    'Contract' => $vendSerialNumber->vend_contract_name,
                    'Customer Name' => $vendSerialNumber->customer_virtual_code . ' (' . $vendSerialNumber->vend_prefix_name . ') ' . $vendSerialNumber->customer_name,
                    'Postcode' => $vendSerialNumber->postcode,
                    'Operator' => $vendSerialNumber->operator_name,
                    'Location Type' => $vendSerialNumber->location_type_name,
                ];
            }
        );
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
            'code' => 'required|unique:vend_serial_numbers,code,' . $id,
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

    private function getMainQuery()
    {
        return VendSerialNumber::query()
            ->with('vend.stickers')
            ->leftJoin('vends', 'vends.vend_serial_number_id', '=', 'vend_serial_numbers.id')
            ->leftJoin('vend_models', 'vend_models.id', '=', 'vends.vend_model_id')
            ->leftJoin('vend_configs', 'vend_configs.id', '=', 'vends.vend_config_id')
            ->leftJoin('vend_contracts', 'vend_contracts.id', '=', 'vends.vend_contract_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
            ->leftJoin('addresses', function ($query) {
                $query->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                    ->where('addresses.type', '=', 2)
                    ->limit(1);
            })
            ->select(
                'addresses.postcode AS postcode',
                'customers.name as customer_name',
                'customers.virtual_customer_code as customer_virtual_code',
                'location_types.name as location_type_name',
                'operators.name as operator_name',
                'vend_serial_numbers.*',
                'vends.id as vend_id',
                'vends.code as vend_code',
                'vends.begin_date as vend_begin_date',
                DB::raw('
                    CASE
                    WHEN vends.lcd_monitor_id = 1 THEN "WaveShare 7 inch 1024x600"
                    WHEN vends.lcd_monitor_id = 2 THEN "WaveShare 10.1 inch 1920x1200"
                    WHEN vends.lcd_monitor_id = 3 THEN "WaveShare 10.1HP-CAPLCD (Type-C) 1280x800"
                    WHEN vends.lcd_monitor_id = 4 THEN "Inhand InPad3101 10.1 inch 1280x800"
                    WHEN vends.lcd_monitor_id = 99 THEN "N/A"
                    ELSE ""
                    END as vend_lcd_monitor'),
                DB::raw('
                    CASE
                    WHEN vends.is_sold = true THEN "Sold"
                    WHEN vends.is_disposed = true THEN "Disposed"
                    WHEN vends.is_testing = true THEN "Factory (JB)"
                    WHEN vends.is_active = true THEN "Active"
                    ELSE "Not Active"
                    END as vend_status
                '),
                'vend_contracts.name as vend_contract_name',
                'vend_models.name as vend_model_name',
                DB::raw('(SELECT vs.name FROM vend_sticker_vend vsv INNER JOIN vend_stickers vs ON vs.id = vsv.vend_sticker_id WHERE vsv.vend_id = vends.id ORDER BY vs.name LIMIT 1) as vend_sticker_name'),
                'vend_configs.name as vend_config_name',
                'vend_prefixes.name as vend_prefix_name'
            );
    }


}
