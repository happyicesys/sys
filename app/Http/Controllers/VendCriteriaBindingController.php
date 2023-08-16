<?php

namespace App\Http\Controllers;


use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\VendChannelErrorResource;
use App\Http\Resources\VendDBResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Customer;
use App\Models\LocationType;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTemp;
use App\Traits\GetUserTimezone;
use App\Traits\HasFilter;
use App\Traits\HasWeightage;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendCriteriaBindingController extends Controller
{
    use GetUserTimezone, HasFilter, HasWeightage;

    public function __construct()
    {
        $this->middleware(['permission: admin-access vends']);
    }

    public function index(Request $request)
    {
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);
        $request->is_binded_customer = auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer != null ? $request->is_binded_customer : 'true');
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'code';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $className = get_class(new Customer());

        $vends = DB::table('vends')
            ->leftJoin('vend_bindings', function($query) {
                $query->on('vend_bindings.vend_id', '=', 'vends.id')
                        ->where('is_active', true)
                        ->latest('begin_date')
                        ->limit(1);
            })
            ->leftJoin('customers', 'customers.id', '=', 'vend_bindings.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('operator_vend', function($query) {
                $query->on('operator_vend.vend_id', '=', 'vends.id')
                        ->latest('operator_vend.begin_date')
                        ->limit(1);
            })
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->select(
                'operator_vend.operator_id',
                'vends.id',
                'vends.code',
                'vends.name',
                'vends.apk_ver_json',
                'vends.serial_num',
                'vends.name',
                'vends.temp',
                'vends.temp_updated_at',
                'vends.firmware_ver',
                DB::raw('DATE(customers.last_invoice_date) AS last_invoice_date'),
                DB::raw('DATE(customers.next_invoice_date) AS next_invoice_date'),
                'vends.balance_percent',
                'vends.last_updated_at',
                'vends.out_of_stock_sku_percent',
                'vends.parameter_json',
                'vends.product_mapping_id',
                'vends.private_key',
                'vends.vend_channels_json',
                'vends.vend_channel_totals_json',
                'vends.vend_channel_error_logs_json',
                'vends.vend_transaction_totals_json',
                'vends.vend_type_id',
                'customers.cms_invoice_history',
                'customers.code AS customer_code',
                'customers.name AS customer_name',
                'customers.location_type_id',
                'location_types.name AS location_type_name',
                'product_mappings.name AS product_mapping_name',
                'product_mappings.remarks AS product_mapping_remarks',
            );
        $vends = $this->filterVendsDB($vends, $request);
        $vends = $vends
            ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('VendCriteriaBinding/Index', [
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
            'productOptions' => ProductResource::collection(
                Product::query()
                    ->select('id', 'code', 'desc', 'name')
                    ->where('is_active', true)
                    ->where('is_inventory', true)
                    ->orderBy('code')
                    ->get()
            ),
            'vendCriteriaBindings' => VendDBResource::collection($vends),
            'vendChannelErrors' => VendChannelErrorResource::collection(VendChannelError::orderBy('code')->get()),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        LocationType::create($request->all());

        return redirect()->route('location-types');
    }

    public function update(Request $request, $locationTypeId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $locationType = LocationType::findOrFail($locationTypeId);
        $locationType->update($request->all());

        $this->recalculateAllWeightage(get_class($locationType));

        return redirect()->route('location-types');
    }

    public function delete($locationTypeId)
    {
        $locationType = LocationType::findOrFail($locationTypeId);
        $locationType->delete();

        return redirect()->route('location-types');
    }
}
